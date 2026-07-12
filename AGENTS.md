# AGENTS.md

UniFi Overview — Symfony 8 / PHP 8.4 / SQLite in Docker.
Parses UniFi Dream Machine support archives (`.tgz`) and shows a persistent device lease table.

## Stack

Single container: Apache + PHP 8.4, SQLite in a Docker volume (`unifi-overview_data`). Dev app starts with:

```bash
docker compose up --build -d   # http://localhost:8080
```

## Commands

```bash
bin/dx php bin/console <cmd>                      # Symfony console in running container
bin/dx php bin/console doctrine:migrations:diff   # Generate a migration
bin/dx php bin/console cache:clear
```

## Quality Gate

Run before every PR — must pass completely:

```bash
bash .quality/gate-full.sh   # builds tester image → phpcs + phpstan + phpunit + Rector
```

## Rules

1. **Never push directly to `master`** — always create a feature branch and open a PR.
2. **Branch naming**: `feature/issue-{nr}-{short-description}` (e.g. `feature/issue-42-dark-mode`)
3. **PR description** must include `Closes #{nr}`.
4. **Gate must be green** before the PR is created.
5. **Commit language**: English, imperative ("Add X", "Fix Y", "Remove Z").
6. **Translations**: all UI strings go in `translations/messages+intl-icu.en.yaml` and `.de.yaml`.

## Golden Examples

Follow these files as templates for new code:

| Type | File |
|---|---|
| Entity | `src/Entity/ClientDevice.php` |
| Service | `src/Service/DhcpConfigParser.php` |
| Service test | `tests/Service/DhcpConfigParserTest.php` |
| Controller | `src/Controller/UploadController.php` |
| Controller test | `tests/Controller/UploadControllerTest.php` |

## Release

1. Bump `"version"` in `composer.json` to the new semver (e.g. `1.1.3`).
2. Add a new section to `## Release Notes` in `README.md` (`### 1.1.3 — YYYY-MM-DD` + bullets).
3. Commit: `Version 1.1.3`.
4. Push commit and tag:
   ```bash
   git tag 1.1.3
   git push origin master
   git push origin 1.1.3
   ```

CI triggers on the tag push: tests → Docker Hub publish → README sync.

## Key Technical Decisions

**System `tar` for .tgz parsing** — `SupportFileParser` uses `exec('tar -xzf ...')` + `RecursiveDirectoryIterator`. Avoids two PharData problems: (1) `@LongLink` entries (paths > 100 chars) crash iteration; (2) PharData decompresses fully into memory → OOM. Temp dir and .tgz copy are cleaned up in a `finally` block.

**Fixture .tgz format** — Must NOT use archive root `.`. Use `tar -C <dir> <name>`, not `tar -C <dir> .`.

**symfony/var-exporter pinned to ^7.2** — Doctrine ORM 3 needs `LazyGhostTrait`, removed in v8.x.

**No composer.lock** — Versions resolve fresh on each Docker build; add a lock file if reproducibility matters.

**Symfony constraint syntax** — Named arguments only: `new File(maxSize: '100M')`. Array syntax removed in Symfony 7.3+.

**Translations** — `symfony/translation` with ICU format (`translations/messages+intl-icu.en.yaml`). Key structure: `{type}.{context}.{element}` — e.g. `label.upload.submit`, `message.upload.success`, `text.overview.count`. Twig: `{{ 'key'|trans }}` or `{{ 'key'|trans({count: n}) }}`. PHP: `TranslatorInterface::trans('key', ['param' => $value])` with ICU-style named params (`{count}`, `{error}`).

**Asset management** — `symfony/asset-mapper` serves Bootstrap and custom CSS/JS without CDN. Bootstrap 5.3.3 files are committed in `assets/vendor/bootstrap/`. The Dockerfile runs `asset-map:compile` during the builder stage → content-hash filenames in `public/assets/` (gitignored). Templates reference assets via `{{ asset('...') }}`.
