# Plan: unifi-overview „Agent Ready" machen

Ziel: Claude Code kann eigenständig GitHub-Issues umsetzen und einen PR erstellen — ohne manuelle Aufsicht.

---

## Was schon vorhanden ist

- `CLAUDE.md` mit Stack-Dokumentation
- `.github/workflows/claude.yml` — reagiert auf Issues und Kommentare mit `@claude`
- `.github/workflows/claude-code-review.yml` — Code-Review bei PRs
- `.claude/settings.local.json` — lokale Docker-Permissions (nicht versioniert)

---

## Was fehlt und warum es stockt

| Problem | Symptom |
|---|---|
| `claude.yml` hat nur `read`-Permissions | Claude kann keine Branches pushen und keine PRs erstellen |
| Kein `bin/dx`-Wrapper | CLAUDE.md erklärt rohe `docker compose exec`-Befehle — im GitHub-Actions-Kontext schwierig |
| Kein versioniertes `.claude/settings.json` | Claude fragt im autonomen Lauf bei jedem Befehl nach |
| Keine Git-Hooks | Quality Gate wird lokal nicht erzwungen (nur dokumentiert) |
| Kein `AGENTS.md` | CLAUDE.md ist zu lang und enthält technische Entscheidungen, die Agenten verwirren |
| Kein System-Prompt im Claude-Action | Agent weiß nicht, dass er einen Branch + PR erstellen soll, nicht direkt auf master pushen |

---

## Die 7 Änderungen

### 1. `AGENTS.md` (neu, ≤ 50 Zeilen)

Single source of truth für alle Agenten: Stack, alle Befehle via `bin/dx`, Quality-Gate-Befehl,
Golden Examples (beste Dateien als Muster für neue Dateien), 3 harte Regeln.
`CLAUDE.md` verweist für Befehle und Regeln nur noch darauf.

### 2. `bin/dx` (neu, ausführbares Skript)

Dünner Wrapper: auf dem Host → `docker compose exec webserver <cmd>`, im Container → direkt.
Vereinfacht alle Befehle:

```bash
bin/dx php bin/console doctrine:migrations:diff
bin/dx php bin/console cache:clear
```

### 3. `.quality/gate-full.sh` (neu)

Ein Befehl für das komplette Quality Gate:
Docker-Image bauen → Tests → phpcs → phpstan → Rector dry-run.
Claude führt dieses Skript vor „fertig" immer aus.

### 4. `.githooks/pre-commit` + `.githooks/pre-push` (neu)

- `pre-commit`: phpcs nur auf geänderte PHP-Dateien (Ziel < 10 s), phpcbf als Auto-Fix zuerst
- `pre-push`: führt `gate-full.sh` aus — Gate kann nicht übersprungen werden

Aktivierung einmalig nach dem Clone:
```bash
git config core.hooksPath .githooks
```

### 5. `.claude/settings.json` (neu, versioniert)

```
Allow:  bin/dx *, git status/log/diff/add/commit/push, docker build/run, find, ls, gh *
Deny:   git commit --no-verify, git push --force, git push origin master
```

Gilt für alle Entwickler und CI-Agenten gleichermaßen.

### 6. `.github/workflows/claude.yml` (Update)

- Permissions ergänzen: `contents: write`, `pull-requests: write`, `issues: write`
- `allowed_tools`: `Bash(bin/dx *)`, `Bash(bash .quality/gate-full.sh)`, `Bash(gh pr *)` u. a.
- System-Prompt: Claude soll immer einen Feature-Branch anlegen und einen PR erstellen,
  niemals direkt auf master pushen, Quality Gate muss grün sein vor dem PR.

### 7. `CLAUDE.md` (schlank machen)

Bleibt als technische Referenz (Key Technical Decisions bleiben erhalten),
verweist aber für alle Befehle und Regeln auf `AGENTS.md`.

---

## Golden Examples (in AGENTS.md benennen)

Die besten vorhandenen Dateien als explizite Vorlage für neue Dateien:

- Entity: `src/Entity/ClientDevice.php`
- Service + Test: `src/Service/DhcpConfigParser.php` + `tests/Service/DhcpConfigParserTest.php`
- Controller + Test: `src/Controller/UploadController.php` + `tests/Controller/UploadControllerTest.php`

---

## Reihenfolge der Umsetzung

1. `bin/dx` + `.quality/gate-full.sh` → Foundation testen
2. `AGENTS.md` + `CLAUDE.md` trimmen → Guidance
3. `.claude/settings.json` → Access
4. `.githooks/` → Feedback (lokal erzwungen)
5. `claude.yml` Permissions + System-Prompt → Autonome PRs

---

## Änderung 8: `README.md` — Abschnitt „Contributing / Development" (neu)

Die README.md richtet sich bisher ausschließlich an Endnutzer. Ein neuer „Contributing"-Abschnitt
erklärt Entwicklern, was sie nach dem Clone einmalig tun müssen, damit:

- das Quality Gate lokal erzwungen wird (Git Hooks)
- Claude Code lokal als Agent funktioniert
- der GitHub-Agent (`@claude` in Issues) für sie verfügbar ist

### Inhalt des neuen Abschnitts (in README.md einfügen, vor „Release Notes")

```markdown
## Contributing / Development

### Prerequisites

- Docker with Compose plugin
- Git
- [Claude Code](https://claude.ai/code) — optional, für lokale Agent-Arbeit

### Setup after cloning

```bash
# 1. App starten
docker compose up --build -d

# 2. Git Hooks aktivieren (einmalig — erzwingt Quality Gate vor jedem Commit und Push)
git config core.hooksPath .githooks
```

That's it. The hooks and the `.claude/settings.json` are versioned — no further configuration needed.

### Quality Gate

Run the full gate manually at any time:

```bash
bash .quality/gate-full.sh
```

This builds the test image and runs phpcs, phpstan, phpunit, and Rector in one go.
All three must be green before a PR can be merged.

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

The `@claude` workflow requires one secret in the repository settings:

| Secret | Where to get it |
|---|---|
| `CLAUDE_CODE_OAUTH_TOKEN` | See „GitHub Setup" section below |
```

---

## GitHub einrichten — Schritt-für-Schritt

Diese Schritte sind einmalig pro Repository und werden nicht durch Code automatisiert.
Sie müssen manuell in der GitHub-Oberfläche durchgeführt werden.

### Schritt 1: OAuth-Token generieren

Der Token authentifiziert Claude gegenüber GitHub. Er wird einmalig erstellt und gehört
immer zu einem bestimmten Anthropic-Account (dem Account, der die PRs und Kommentare erstellt).

1. Auf **[claude.ai](https://claude.ai)** einloggen
2. Oben rechts auf das Profilbild klicken → **Settings**
3. Im linken Menü: **Claude Code**
4. Abschnitt **„OAuth Tokens"** → Button **„Create new token"**
5. Name vergeben (z. B. `unifi-overview GitHub Actions`)
6. Token kopieren — er wird nur einmal angezeigt

### Schritt 2: Token als Repository Secret hinterlegen

1. Im GitHub-Repository: **Settings** → **Secrets and variables** → **Actions**
2. Button **„New repository secret"**
3. Name: `CLAUDE_CODE_OAUTH_TOKEN`
4. Value: Den kopierten Token einfügen
5. **„Add secret"** klicken

### Schritt 3: Branch Protection für `master` aktivieren

Verhindert, dass Claude (oder ein Entwickler) direkt auf `master` pusht.

1. Im GitHub-Repository: **Settings** → **Branches**
2. Button **„Add branch ruleset"** (oder „Add classic rule" bei älteren Repos)
3. Branch name pattern: `master`
4. Folgende Optionen aktivieren:
   - ✅ **Require a pull request before merging**
   - ✅ **Require status checks to pass before merging** → CI-Job aus `.github/workflows/ci.yml` auswählen
5. **„Create"** klicken

### Wie ein Issue Claude triggert

Nachdem alles eingerichtet ist, genügt folgendes Schema beim Erstellen eines Issues:

```
Titel:  Add dark mode support
Text:   @claude Please implement this.

Acceptance criteria:
- Toggle button in the navbar
- Preference saved in localStorage
- Works on all existing pages
```

Claude startet automatisch, legt einen Branch an, implementiert das Feature,
führt das Quality Gate aus und öffnet einen PR mit „Closes #<nr>" in der Beschreibung.

---

## Reihenfolge der Umsetzung

1. `bin/dx` + `.quality/gate-full.sh` → Foundation testen
2. `AGENTS.md` + `CLAUDE.md` trimmen → Guidance
3. `.claude/settings.json` → Access
4. `.githooks/` → Feedback (lokal erzwungen)
5. `claude.yml` Permissions + System-Prompt → Autonome PRs
6. GitHub einrichten (Token, Secret, Branch Protection) → einmalig, manuell

---

## Definition of Done

- `bash .quality/gate-full.sh` läuft grün durch
- Ein Test-Issue auf GitHub mit `@claude` triggert den Workflow, Claude legt einen Branch an,
  implementiert die Aufgabe, Gate wird grün, PR wird erstellt — ohne manuelle Eingriffe
