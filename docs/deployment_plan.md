# Deployment-Plan für externes Hosting mit CI/CD

Dieser Plan beschreibt den schnellstmöglichen Weg, um die Anwendung auf einem externen Hosting-Anbieter mit automatischer CI/CD-gestützter Auslieferung zu bringen.

## 1. Bereinigen und Repo sauber halten

- Entferne lokale Artefakte aus dem Repository:
  - `composer.phar`
  - `version.txt`
- Achte darauf, dass folgende Dateien nur lokal bleiben und nicht committed werden:
  - `.env.local`, `.env`
  - `logs/`
  - `content/tmp/`
  - `react-dist/`
  - `frontend/node_modules/`
  - `.phpunit.cache/`, `.phpstan-cache/`
  - `.cursor/`

## 2. CI/CD-Workflow aufsetzen

Bereits erstellt im Repo:

- `.github/workflows/deploy.yml`
- `scripts/remote-deploy.sh`

Diese Komponenten sollen sicherstellen, dass ein erfolgreicher GitHub-Action-Lauf auf `main` den Code automatisch zum Host überträgt.

## 3. GitHub-Secrets konfigurieren

Im Repository müssen folgende Secrets gesetzt werden:

- `SSH_HOST`
- `SSH_USER`
- `SSH_PRIVATE_KEY`
- `DEPLOY_PATH`
- optional: `SSH_KNOWN_HOSTS`
- optional: `REMOTE_RESTART_COMMAND`

## 4. Zielhost vorbereiten

Der Zielserver muss bereit sein für das Deployment:

- Git-Repository im `DEPLOY_PATH`
- `php` installiert
- `composer` installiert
- `node` und `npm` installiert
- `bash` verfügbar
- Produktions-Umgebungsdateien:
  - `/.env.local` aus `.env.production.example`
  - `laravel/.env` mit:
    - `APP_ENV=production`
    - `APP_DEBUG=false`
    - `APP_URL=https://<host>`
    - `LEGACY_SITE_URL=https://<host>`
    - `HANDOFF_SECRET`
    - `SESSION_SECURE=1`
    - `TRUSTED_PROXY_IPS`

## 5. Testlauf und Validierung

1. Lokale Validierung:
   - `make prod-env-check`
   - `make deploy-check`
2. Testdeploy per GitHub Actions:
   - `workflow_dispatch` oder Push auf `main`
3. Nach Deploy prüfen:
   - HTTPS-Zugriff auf die Website
   - `Admin → Deploy-Status`
   - Hybrid-Login/Brücke testen

## 6. Weiteres Vorgehen

- Falls der Server kein `bash` hat, alternativ ein reines SSH-/Shell-Skript anpassen.
- Bei Bedarf später Staging als separaten Branch oder Zielhost einrichten.
- Deployment-Logik in `.github/workflows/deploy.yml` und `scripts/remote-deploy.sh` bei Bedarf enger auf die Zielumgebung abstimmen.
