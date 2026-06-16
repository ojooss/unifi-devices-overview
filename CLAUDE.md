# CLAUDE.md

Symfony 8 web app in Docker. Parses UniFi Dream Machine support archives (`.tgz`), stores DHCP lease data in SQLite. Single container: Apache + PHP 8.4, SQLite in a Docker volume (`unifi-overview_data`).

## Run / Build

```bash
docker compose up --build -d   # start or rebuild
docker compose down
```

App: **http://localhost:8080**. Entrypoint runs `doctrine:migrations:migrate` + `cache:warmup` on every start.

## Tests

```bash
docker build --target tester -t unifi-overview-test .
docker run --rm unifi-overview-test
```

Service/Entity tests: pure unit tests, no DB. Controller tests create a real SQLite schema via `SchemaTool`.

## Quality Gate

After every change, all must pass before considering the task done:

```bash
# 1. rebuild image to pick up changes
docker build --target tester -t unifi-overview-test .

# 2. phpcs + phpstan + phpunit (runs all three via Dockerfile CMD, chained with &&)
docker run --rm unifi-overview-test

# 3. Rector (should report 0 changes)
docker run --rm -v $(pwd)/rector.php:/var/www/html/rector.php -v $(pwd)/src:/var/www/html/src -v $(pwd)/tests:/var/www/html/tests unifi-overview-test vendor/bin/rector process --dry-run
```

## Add a Migration

```bash
docker compose exec webserver php bin/console doctrine:migrations:diff
```

## Key Technical Decisions

**System `tar` for .tgz parsing** — `SupportFileParser` uses `exec('tar -xzf ...')` + `RecursiveDirectoryIterator`. Avoids two PharData problems: (1) `@LongLink` entries (paths > 100 chars) crash iteration; (2) PharData decompresses fully into memory → OOM. Temp dir and .tgz copy are cleaned up in a `finally` block.

**Fixture .tgz format** — Must NOT use archive root `.`. Use `tar -C <dir> <name>`, not `tar -C <dir> .`.

**symfony/var-exporter pinned to ^7.2** — Doctrine ORM 3 needs `LazyGhostTrait`, removed in v8.x.

**No composer.lock** — Versions resolve fresh on each Docker build; add a lock file if reproducibility matters.

**Symfony constraint syntax** — Named arguments only: `new File(maxSize: '100M')`. Array syntax removed in Symfony 7.3+.

**Translations** — `symfony/translation` with ICU format (`translations/messages+intl-icu.en.yaml`). Key structure: `{type}.{context}.{element}` — e.g. `label.upload.submit`, `message.upload.success`, `text.overview.count`. Twig: `{{ 'key'|trans }}` or `{{ 'key'|trans({count: n}) }}`. PHP: `TranslatorInterface::trans('key', ['param' => $value])` with ICU-style named params (`{count}`, `{error}`).

**Asset management** — `symfony/asset-mapper` serves Bootstrap and custom CSS/JS without any CDN at runtime. Bootstrap 5.3.3 files are committed in `assets/vendor/bootstrap/`. The Dockerfile runs `asset-map:compile` during the builder stage to copy all assets with content-hash filenames to `public/assets/` (gitignored). Templates reference assets via `{{ asset('...') }}`.
