# Deployment

**Arbeitsweise:** Bis auf Weiteres wird nur **lokal** entwickelt. Dieses Dokument beschreibt die lokale Infrastruktur (Docker) und einen **Entwurf** für späteres Hosting — ohne verbindlichen Produktions-Rollout.

## Lokal: Docker-Dienste

MongoDB und MailHog lassen sich per Compose starten; PHP und Laravel laufen weiter auf dem Host (Ports wie in `AGENTS.md` / `Makefile`).

```bash
docker compose up -d
# oder: make services-up
```

| Dienst   | Port (Host) | Nutzung |
|----------|-------------|---------|
| MongoDB  | 127.0.0.1:27017 | Nur Loopback (siehe `docker-compose.yml`) |
| MailHog SMTP | 127.0.0.1:1025 | `MAIL_SMTP_HOST=127.0.0.1`, `MAIL_SMTP_PORT=1025` |
| MailHog UI   | 127.0.0.1:8025 | http://127.0.0.1:8025/ |

**Nach erstem Mongo-Start:** Datenbank und Mongo-Benutzer wie gewohnt über Admin **DB-Skripte** (`03_db_init_mongo_roles.php`, ggf. `db_init_master.php`) anlegen. Ohne Auth in der URI funktioniert die App erst nach dieser Initialisierung.

```bash
make frontend-build   # einmalig / nach CSS-Änderung
make php              # http://127.0.0.1:8080/
make laravel          # http://127.0.0.1:8000/ (Passwort-Reset)
```

Stoppen: `docker compose down` bzw. `make services-down`. Daten bleiben im Volume `mongo_data`.

## Lokal ohne Docker

- MongoDB: eigene Installation oder Remote-URI in `.env.local`
- Mail: `make mailhog` (einzelner Container) oder System-`mail()`

Siehe `Makefile`, `README.md`, `docs/local_next_steps.md`.

## Später: Server + DynDNS (Entwurf)

Noch **nicht** umgesetzt im Repo. Grober Ablauf, wenn ein eigener Server bereitsteht:

1. **DNS:** DynDNS (oder statische IP) auf den Server; Subdomain für die Site und optional für Laravel-Auth.
2. **TLS:** Reverse-Proxy (z. B. Caddy oder nginx) mit Let's Encrypt; `SESSION_SECURE=1`, konsistente Basis-URL (`LEGACY_SITE_URL`, `APP_URL`).
3. **Prozessmodell (Variante A — einfach):** PHP-FPM + nginx für Projektroot; Laravel als zweite Site oder Subpath; MongoDB nur intern erreichbar.
4. **Prozessmodell (Variante B — Container):** Compose mit `mongodb`, App-Image (PHP), Laravel-Image; Secrets nur in `.env` auf dem Server, nicht im Git.
5. **Secrets:** `HANDOFF_SECRET`, Mongo-Passwörter, SMTP — getrennt von Dev; keine `APP_ALLOW_DEV_DB_DEFAULTS` in Produktion.
6. **Medien:** `content/images`, `content/videos` persistent mounten; Backups für Mongo + Medien.
7. **Mail:** SMTP mit TLS (kein MailHog); Laravel und/oder `includes/mail.php` auf denselben Provider.

Offene Punkte vor Go-Live: Firewall (nur 80/443 öffentlich), Rate-Limits, Log-Rotation, Monitoring.

## Checkliste vor erstem Server-Deploy

- [ ] `react-dist/` gebaut (`cd frontend && npm run build`)
- [ ] `.env.local` / Server-`.env` mit Produktions-URIs (keine Dev-Defaults)
- [ ] `laravel/.env`: `APP_ENV=production`, `APP_DEBUG=false`, Handoff-URLs mit HTTPS
- [ ] Mongo-Rollen aus `03_db_init_mongo_roles.php` (kein offenes Mongo ohne Auth)
- [ ] `APP_ENV=production`, `APP_ALLOW_DEV_DB_DEFAULTS=0`, explizite Mongo-URIs
- [ ] `SESSION_SECURE=1`, `TRUSTED_PROXY_IPS` hinter TLS-Terminierung
- [ ] `dbScripts/14_db_init_handoff_tokens.php` (Handoff-Nonces)
- [ ] Upload-Limits (PHP + nginx); **kein SVG** als Bild-Upload
- [ ] `Referrer-Policy` / CSP aktiv (automatisch via `includes/security_headers.php`)
- [ ] Smoke-Test: Login, Register mit Code, Handoff-Replay blockiert, Projekt, Admin DB-Skripte

Weitere Sicherheit: `docs/security_roadmap.md`, `docs/next_session_plan.md`.
