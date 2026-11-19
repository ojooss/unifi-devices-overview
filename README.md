# UniFi Overview

A self-hosted web application that keeps a persistent overview of all devices seen on your UniFi Dream Machine network. 
Upload a support file and instantly see which device (hostname + MAC address) was assigned which IP — and whether that assignment was fixed or dynamic.

## What it does

- Scan the Dream Machine directly via the UniFi API (button or `app:unifi:scan` command)
- Import UniFi support archives (`.tgz`) via a simple upload form
- Shows a filterable table of all device leases: hostname, MAC, IP address, IP type (fixed / dynamic), network, and timestamps
- Re-uploading the same file is safe — records are updated, not duplicated
- Filter by network, IP type, and free-text search (hostname or MAC address)
- Assign a custom display name and a free-text remark to any lease entry

All data is stored locally in a SQLite database inside a Docker volume — nothing leaves your machine.

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) with the Compose plugin (or Docker Desktop)

## Installation

### Option A — Docker Hub (recommended)

Create a `docker-compose.yml` with the following content:

```yaml
services:
  webserver:
    image: ojooss/unifi-devices-overview:latest
    restart: always
    environment:
      - APP_SECRET=replace_this_with_a_random_32char_string
      - APP_LOCALE=en
    volumes:
      - data:/var/www/html/var/data
    ports:
      - "8080:80"

volumes:
  data:
```

Then start the application:

```bash
docker compose up -d
```

### Option B — build from source

```bash
git clone https://github.com/your-user/unifi-overview.git
cd unifi-overview
```

Before the first start, set a secret key in `.env`:

```bash
# replace with any random 32-character string
APP_SECRET=replace_this_with_a_random_32char_string
```

Then start the application:

```bash
docker compose up -d
```

The first start builds the Docker image and creates the database automatically.

---

Open **http://localhost:8080** once the container is running.

## Getting a support file from your UniFi Dream Machine

1. Log in to your UniFi Dream Machine's management interface
2. Go to **Settings → System → Support File**
3. Click **Download Support File** — this produces a `.tgz` archive

The filename contains a millisecond timestamp (e.g. `support-XXXX-1718000000000.tgz`) which the application uses to record *when* the leases were observed.

## Scanning the Dream Machine directly (API key)

Instead of uploading a support file you can let the application query the Dream Machine itself. The scan reads all clients the controller knows (including devices that are currently offline), creates new entries and updates existing ones by MAC address. Custom names and remarks are never overwritten. Support-file import keeps working alongside the scan, for example to fill in lease expiry dates, which the API does not provide.

### 1. Create an API key

1. Log in to your UniFi Dream Machine's management interface and open the **Network** application
2. Find the API key page of the Network application. In recent versions it is called **Integrations**; if you cannot find it in the settings list, use the search field at the top of the settings or look for the "Integrations" entry in the icon bar on the left
3. Click **Create API Key**, enter a name such as `unifi-overview`, and confirm
4. Copy the key right away — it is shown only once. If you lose it, delete it and create a new one

### 2. Check that the key works

```bash
read -rs UNIFI_API_KEY && export UNIFI_API_KEY   # paste the key, press Enter
curl -sk -H "X-API-KEY: $UNIFI_API_KEY" https://<udm-address>/proxy/network/api/s/default/rest/user | head -c 500
```

A JSON response starting with `{"meta":{"rc":"ok"},"data":[...` means the key is valid. `-k` skips the certificate check, which is needed when the Dream Machine uses its default self-signed certificate.

### 3. Configure the application

Treat the key like a password and never commit it. Put the settings in `.env.local` (ignored by Git) and start the container with it:

```bash
UNIFI_BASE_URL=https://<udm-address>
UNIFI_API_KEY=<your-key>
UNIFI_VERIFY_TLS=false   # only for the default self-signed certificate
```

```bash
docker compose --env-file .env.local up -d
```

`--env-file` *replaces* the `.env` file that Docker Compose reads by default, it does not add to it. Everything Compose substitutes into `docker-compose.yml` must therefore be in `.env.local`: the `UNIFI_*` values and, if you do not want the default `en`, also `APP_LOCALE` (for example `APP_LOCALE=de`). All other settings (`APP_SECRET`, `DATABASE_URL`) are unaffected, because the application reads the `.env` file inside the image itself. `.env.local` is excluded from the image via `.dockerignore`, so your key never ends up in it.

See [Configuration](#configuration) for all variables. Without `UNIFI_API_KEY` the scan button is hidden.

### 4. Run a scan

- Click **Scan UniFi** on the overview page, or
- run it on a schedule: set `UNIFI_SCAN_SCHEDULE` to a cron expression, e.g. `UNIFI_SCAN_SCHEDULE=0 * * * *` for hourly (container time, UTC by default). The container then runs the scan itself, output goes to `docker logs`. No cron job on the host is needed.
- or run it manually: `docker compose exec webserver php bin/console app:unifi:scan`

## Using the application

### Import a support file

1. Open **http://localhost:8080**
2. Click **"Supportdatei importieren"**
3. Select your `.tgz` file and submit

The import typically finishes in a few seconds. Uploading the same file again is harmless.

### Browse and filter

The overview table shows all imported lease entries. Use the filter controls at the top to narrow results by:

- **Network** — e.g. `LAN`, `DMZ`, `IoT`
- **IP type** — `fixed` (statically configured via `dhcp-host`) or `dynamic`
- **Search** — free-text match on hostname or MAC address

### Custom names and remarks

Click on any entry's name or remark cell to edit it inline. Changes are saved immediately. 
This is useful for giving human-readable labels to devices that report no hostname.

## Configuration

All configuration is done in the `.env` file or via environment variables passed to Docker Compose.

| Variable       | Default                                | Description                                     |
|----------------|----------------------------------------|-------------------------------------------------|
| `APP_SECRET`   | *(value in `.env`)*                    | Secret key — **change this before deploying**   |
| `APP_LOCALE`   | `en`                                   | UI language — `en` (English) or `de` (German)  |
| `APP_ENV`      | `prod`                                 | Symfony environment, leave as `prod`            |
| `DATABASE_URL` | `sqlite:///.../var/data/unifi.db`      | Database location, no need to change            |
| `UNIFI_BASE_URL` | *(empty)*                            | Address of the Dream Machine, e.g. `https://192.168.1.1` |
| `UNIFI_API_KEY`  | *(empty)*                            | API key for the scan; empty disables the scan   |
| `UNIFI_SITE`     | `default`                            | UniFi site name                                 |
| `UNIFI_VERIFY_TLS` | `true`                             | Set to `false` for a self-signed certificate    |
| `UNIFI_SCAN_SCHEDULE` | *(empty)*                       | Cron expression for a scheduled scan, e.g. `0 * * * *`; empty disables it |

### Language

The UI language is controlled by `APP_LOCALE`. Set it in `.env` for a permanent change:

```dotenv
APP_LOCALE=de
```

Or pass it on the command line for a one-off start:

```bash
APP_LOCALE=de docker compose up -d
```

### Changing the port

The application listens on port `8080` by default. To use a different port, edit `docker-compose.yml`:

```yaml
ports:
  - "9090:80"   # change 9090 to your preferred port
```

Then restart: `docker compose up -d`.

## Data and backups

All data is stored in a Docker volume named `unifi-overview_data`. To back it up, copy the SQLite database out of the running container:

```bash
docker compose cp webserver:/var/www/html/var/data/unifi.db ./unifi-backup.db
```

## Updating

```bash
git pull
docker compose up --build -d
```

The container automatically runs any pending database migrations on startup.

## Contributing / Development

### Prerequisites

- Docker with Compose plugin
- Git
- [Claude Code](https://claude.ai/code) — optional, for local agent work

### Setup after cloning

```bash
# 1. Start the app
docker compose up --build -d

# 2. Activate Git hooks (once — enforces the quality gate before every commit and push)
git config core.hooksPath .githooks
```

That's it. The hooks and `.claude/settings.json` are versioned — no further configuration needed.

### Quality gate

Run the full gate manually at any time:

```bash
bash .quality/gate-full.sh
```

Builds the test image and runs phpcs, phpstan, phpunit, and Rector in one go.
All checks must pass before a PR can be merged.

### Working with Claude Code

**In GitHub (no local setup needed):**
Mention `@claude` in any issue or PR comment — Claude Code will pick up the task,
implement it on a feature branch, run the quality gate, and open a pull request automatically.

**Locally:**

```bash
claude   # interactive mode, reads AGENTS.md automatically
```

For autonomous single-task runs:

```bash
claude --permission-mode acceptEdits -p "Implement issue #42. Follow AGENTS.md. Gate must be green before done."
```

### GitHub Action secrets

The `@claude` workflow requires one secret in the repository settings
(**Settings → Secrets and variables → Actions → New repository secret**):

| Secret | Value |
|---|---|
| `CLAUDE_CODE_OAUTH_TOKEN` | Generate at [claude.ai](https://claude.ai) → Settings → Claude Code → OAuth Tokens → Create new token |

---

## Release Notes

### 1.5.0 — 2026-11-19

- **New:** Scheduled UniFi scan inside the container. Set `UNIFI_SCAN_SCHEDULE` to a cron expression (e.g. `0 * * * *`) and the container runs `app:unifi:scan` on that schedule; output goes to `docker logs`. No cron job on the host is needed.

### 1.4.0 — 2026-11-16

- **New:** Scan the Dream Machine directly via the UniFi API (button on the overview or `app:unifi:scan` command). The scan adds new devices and updates known ones by MAC address; it needs `UNIFI_BASE_URL` and `UNIFI_API_KEY`, see the setup section.
- **Changed:** The overview shows the custom name, the UniFi alias and the hostname in separate, sortable columns. The text search now covers all of them plus remark, MAC and IP address. The filters are ordered search, IP type, network.
- **New:** The UniFi scan checks ports 80, 8080 and 443 on every device. If one is open, the IP address on the overview links to it (https on 443, otherwise http).
- **Changed:** The Overview and Import links are removed from the navigation bar. The Import button on the overview and the title link remain.

### 1.3.0 — 2026-07-12

- **New:** Quick-help section on the upload page explains how to export a UniFi support file from the UniFi OS console.

### 1.2.0 — 2026-07-05

- **Improved:** Remark field is now included in the free-text search filter (alongside hostname and MAC address).
- **Improved:** GitHub Actions workflow refined — conditional Docker Hub publishing and metadata updates, better concurrency control.

### 1.1.2 — 2026-06-17

- **Fixed:** Uniqueness of device records is now enforced on MAC address only — a device that appears on multiple networks or is re-imported no longer creates duplicate entries.

### 1.1.1 — 2026-06-17

- **Fixed:** Timestamps in the *Last Updated* and *Last Seen* columns are now displayed at full contrast (previously rendered in muted/grey text).

### 1.1.0 — 2026-06-16

- **New:** Device aliases from the UniFi network topology are now imported and shown in the overview table, giving human-readable names to devices that report no hostname.
- **Improved:** File upload validation now shows clear, translated error messages when a file is missing, of the wrong type, or too large.
- **Improved:** Upload form renders with Bootstrap 5 styling for a consistent look.

### 1.0.0 — 2026-06-16

Initial public release.

- Import UniFi Dream Machine support archives (`.tgz`) via a simple upload form.
- Overview table showing hostname, MAC address, IP address, IP type (fixed/dynamic), network, and timestamps.
- Inline editing of a custom display name and free-text remark per device.
- Filter by network, IP type, and free-text search across hostname and MAC address.
- CSV export of the current filtered table view.
- English and German UI language support (`APP_LOCALE=en` / `APP_LOCALE=de`).
