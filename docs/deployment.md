# Deployment

Dieses Dokument beschreibt lokale Infrastruktur für Entwicklung und Tests sowie den Weg zum extern gehosteten Produktionsbetrieb. Vor dem ersten externen Setup: [smoke_test.md](smoke_test.md), `make prod-env-check`, Checkliste unten.

## Lokal: Docker-Dienste

MongoDB und MailHog lassen sich per Compose starten; PHP und Laravel laufen auf dem Host (Ports wie in `AGENTS.md` / `Makefile`).

```bash
docker compose up -d
# oder: make services-up
```

| Dienst   | Port (Host) | Nutzung |
|----------|-------------|---------|
| MongoDB  | 127.0.0.1:27017 | Nur Loopback (siehe `docker-compose.yml`) |
| MailHog SMTP | 127.0.0.1:1025 | `MAIL_SMTP_HOST=127.0.0.1`, `MAIL_SMTP_PORT=1025` (ohne TLS) |
| MailHog UI   | 127.0.0.1:8025 | http://127.0.0.1:8025/ |

**PHP + Laravel auf MailHog:** [local_mail_setup.md](local_mail_setup.md) (zwei Env-Dateien, `make mailhog-check`). Diese lokalen Dienste sind für Entwicklung und Tests gedacht; für externes Hosting verwenden Produktion und Staging eigene SMTP- und DB-Verbindungen.

```bash
make frontend-build
make php              # http://127.0.0.1:8080/
make laravel          # http://127.0.0.1:8000/
make deploy-check     # CLI wie Admin → Deploy-Status
make prod-env-check   # APP_ENV=production-Overlay (siehe Hinweise unten)
make db-baseline      # Skripte 16, 15, 14 per CLI (idempotent)
```

Stoppen: `docker compose down` bzw. `make services-down`.

## Produktions-Env (Dry-Run lokal)

1. Vorlage: [`.env.production.example`](../.env.production.example) — auf dem Server als `.env.local` anlegen (nicht committen).
2. Optional lokal: Kopie nach `.env.production.local` und Werte anpassen; `prod-env-check.sh` lädt diese Datei zusätzlich.
3. Ausführen:

```bash
make prod-env-check
```

**Erwartung lokal:** Oft 1–2 rote Prüfungen, solange `laravel/.env` noch `APP_DEBUG=true` und MailHog ohne TLS läuft — auf dem Server beheben. Mongo-URIs und `APP_ALLOW_DEV_DB_DEFAULTS=0` sollten grün sein.

## E-Mail in Produktion

Klassische PHP-App (`includes/mail.php`):

| Variable | Bedeutung |
|----------|-----------|
| `MAIL_SMTP_HOST` | SMTP-Server |
| `MAIL_SMTP_PORT` | z. B. `587` (STARTTLS) oder `465` (SSL) |
| `MAIL_SMTP_ENCRYPTION` | `tls`, `ssl` oder `none` (nur lokal/MailHog) |
| `MAIL_SMTP_USERNAME` / `MAIL_SMTP_PASSWORD` | Auth beim Provider |
| `MAIL_FROM_EMAIL` | Absender |

In `APP_ENV=production` blockiert Plain-SMTP (`encryption=none`) den Versand und schlägt im Deploy-Status fehl.

Laravel (Passwort-Reset): `laravel/.env` mit `MAIL_MAILER=smtp`, `MAIL_ENCRYPTION=tls`, Provider-Credentials — siehe `laravel/.env.example`.

## Server: Reverse-Proxy (Beispiele im Repo)

- [deploy/nginx.example.conf](../deploy/nginx.example.conf) — TLS, `client_max_body_size`, Block für `/content/`, `/logs/`, `/dbScripts/`
- [deploy/Caddyfile.example](../deploy/Caddyfile.example) — automatisches TLS, gleiche Block-Regeln

Für externes Hosting gilt:

- öffentlicher Zugriff nur über HTTPS
- die Anwendung hinter einem Reverse-Proxy betreiben
- die MongoDB nicht direkt aus dem Internet erreichbar machen

Nach dem Proxy:

- `SESSION_SECURE=1` im Projektroot-`.env.local`
- `TRUSTED_PROXY_IPS` = IP des Reverse-Proxys (z. B. `127.0.0.1` oder interne Proxy-IP)
- `LEGACY_SITE_URL` / `APP_URL` in `laravel/.env` mit **HTTPS** und korrektem Host/Port

## Server-Update (Code)

Nicht über das Admin-Panel — per SSH im Projektroot:

```bash
git pull
make deploy-server
# oder manuell:
# composer install --no-dev --optimize-autoloader
# cd frontend && npm ci && npm run build
# cd laravel && composer install --no-dev && php artisan config:cache && php artisan migrate
make deploy-check
php scripts/ensure-db-baseline.php
```

Zusätzlich kann das Projekt per GitHub Actions auf `main` automatisch deployt werden, wenn das GitHub-Environment `production` eingerichtet ist. Secrets: `SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY`, `SSH_KNOWN_HOSTS`, `DEPLOY_PATH`; Variablen: `PRODUCTION_URL` und optional `PHP_FPM_SERVICE`. Die Workflow-Datei heißt `.github/workflows/deploy.yml` und führt auf dem Zielhost folgende Schritte aus:

- exakt den zuvor von CI geprüften `main`-Commit auschecken
- Composer-Installationen und Optimierungen ausführen
- Frontend-Assets bauen
- Laravel-Migrationen ausführen und Cache konfigurieren
- DB-Baseline prüfen (`scripts/ensure-db-baseline.php`)
- Deploy-Checks ausführen (`scripts/deploy-check.php`)

Optional lädt `PHP_FPM_SERVICE` (zum Beispiel `php8.4-fpm`) PHP-FPM nach erfolgreichem Deploy neu; beliebige Shell-Kommandos aus Secrets werden nicht ausgeführt.

Danach im Browser: **Admin → Deploy-Status**, ausstehende **DB-Skripte** (Re-Auth). `03_db_init_mongo_roles.php` nur mit **Prod-Passwörtern** und nie `db_init_master` auf bestehender DB ohne Freigabe.

## Betrieb (Firewall, Backups, Logs)

| Thema | Empfehlung |
|--------|------------|
| Firewall | Nur 80/443 öffentlich; MongoDB nur intern |
| Backups | Regelmäßig Mongo (`mongodump`) + `content/images`, `content/videos` |
| `logs/` | Nicht öffentlich (nginx/Caddy deny; siehe Beispiel-Configs) |
| Log-Rotation | `logrotate` für `logs/*.log` |
| Monitoring | Optional Uptime + Disk; nicht im Repo |

## Checkliste vor erstem Server-Deploy

- [ ] `react-dist/` gebaut (`make frontend-build` / `make deploy-server`)
- [ ] Projektroot `.env.local` aus `.env.production.example` (Produktions-URIs, `APP_ALLOW_DEV_DB_DEFAULTS=0`)
- [ ] `laravel/.env`: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS-URLs, `HANDOFF_SECRET` (≥32 Zeichen, neu)
- [ ] Mongo-Rollen: `03_db_init_mongo_roles.php` (kein offenes Mongo)
- [ ] `SESSION_SECURE=1`, `TRUSTED_PROXY_IPS` hinter TLS
- [ ] `make db-baseline` oder Skripte 14/15/16 im Admin
- [ ] `cd laravel && php artisan migrate`
- [ ] Mail: `MAIL_SMTP_ENCRYPTION=tls` (PHP) und/oder Laravel SMTP mit TLS
- [ ] `make deploy-check` und Admin **Deploy-Status** ohne Fehler
- [ ] GitHub-Environment `production`, Deploy-Workflow und Zielhost-Secrets/Variablen gemäß `docs/deployment_plan.md` konfiguriert
- [ ] Smoke-Test: [docs/smoke_test.md](smoke_test.md)

Weitere Sicherheit: [security_roadmap.md](security_roadmap.md), [next_session_plan.md](next_session_plan.md).
