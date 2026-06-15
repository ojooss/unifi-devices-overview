# UniFi Overview

A self-hosted web application that keeps a persistent overview of all devices seen on your UniFi Dream Machine network. 
Upload a support file and instantly see which device (hostname + MAC address) was assigned which IP — and whether that assignment was fixed or dynamic.

## What it does

- Import UniFi support archives (`.tgz`) via a simple upload form
- Shows a filterable table of all device leases: hostname, MAC, IP address, IP type (fixed / dynamic), network, and timestamps
- Re-uploading the same file is safe — records are updated, not duplicated
- Filter by network, IP type, and free-text search (hostname or MAC address)
- Assign a custom display name and a free-text remark to any lease entry

All data is stored locally in a SQLite database inside a Docker volume — nothing leaves your machine.

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) with the Compose plugin (or Docker Desktop)

## Installation

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

The first start builds the Docker image and creates the database automatically. Open **http://localhost:8080** once the container is running.

## Getting a support file from your UniFi Dream Machine

1. Log in to your UniFi Dream Machine's management interface
2. Go to **Settings → System → Support File**
3. Click **Download Support File** — this produces a `.tgz` archive

The filename contains a millisecond timestamp (e.g. `support-XXXX-1718000000000.tgz`) which the application uses to record *when* the leases were observed.

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
