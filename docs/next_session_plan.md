# Plan für neue Session (Stand 2026-05-20)

Kurzüberblick zum Weitermachen — Zielbild, erledigt, offen, Test.

## Zielbild Auth (bestätigt)

| Thema | Entscheidung |
|--------|----------------|
| Laravel | **Vorerst nur** Login (`bridge_auth.php`), Registrierung mit Invite-Code (`bridge_register.php`), Passwort-Reset. **Kein** Laravel `/verify-email` für die klassische Site. |
| Registrierung A (Anfrage) | `pages/register.php` → E-Mail-Link `verify_registration_request` → Admin `registration_requests` → Code per Mail → Konto mit Code. |
| Registrierung B (Admin) | `pages/admin/invite_codes.php` → Code → `pages/register.php` / `bridge_register.php`. |
| E-Mail nach Login | **Kein** Zwang. `email_verified_at` bei Registrierung (Admin-Code oder passende verifizierte Anfrage). |
| 2FA | **Optional**, TOTP in **klassischer PHP** — eigene Seite `index.php?page=two_factor` (Erklärung, QR, Verwaltung); Login nur Passwort + ggf. `?step=2fa`. **Kein** E-Mail-2FA. |

## Erledigt (Repo, lokal)

### Sprint 1 — Session & CSRF
- CSRF Legacy-POST, Session-Cookies, Handoff `session_regenerate_id`, POST-Logout, `APP_ALLOW_DEV_DB_DEFAULTS`, Rate-Limiter Proxy.

### Sprint 2 — Mongo RBAC
- `03_db_init_mongo_roles.php`: App-Rollen ohne `users`/`registration_codes`; `registration_code_requests` für Gäste; `user_db.php`; Projekt-Indizes.

### Sprint 3 — AuthZ & Registrierung
- `includes/authz.php`, kein Laravel-E-Mail-Gate, `resolveEmailVerifiedAt()`, Admin-CSRF vollständig, `MustVerifyEmail` entfernt.

### Sprint 4 — Optionales TOTP-2FA
- `includes/two_factor.php`, `includes/two_factor_handlers.php`, `bridge_auth.php` / `bridge_auth_2fa.php`.
- **`pages/two_factor.php`**: Erklärung, QR (`js/qrcode.bundle.js`), Einrichtung/Backup/Deaktivieren.
- **`pages/account.php`**: nur Status + Link zur 2FA-Seite (kein Setup im Account/Login-Formular).
- `pages/login.php`: nur Login + optional `step=2fa` (kein Setup).
- `dbScripts/11_db_init_users_two_factor.php`; Frontend: `npm run build:qrcode`.

**Branch:** `main`, **14+ Commits** vor `origin/main` (lokal nicht gepusht).

## Nächste Session — Start hier (P3)

### P3 — Betrieb / Mongo
- `03_db_init_mongo_roles.php` nach RBAC-Änderung im Admin ausführen; `ADMIN_DB_URI` prüfen.
- `registration_codes`-Insert nur mit Admin-DB-User (RBAC).
- Nach `db_init_master`: ggf. neu einloggen (Session `user_id`).
- Optional: `11_db_init_users_two_factor.php` (Index).

### P4 — Später
- Admin Re-Auth vor destruktiven DB-Aktionen (`db_scripts.php`).
- Deployment Docker/DynDNS.
- Frontend/CSS (`current_status.md` UI-Backlog).

## Lokaler Test (kurz)

```bash
php -S 127.0.0.1:8080 -t .
cd laravel && php artisan serve --host=127.0.0.1 --port=8000
# Nach CSS-Änderung:
cd frontend && npm run build
```

**Checks (2FA):**
- Account → Link „2FA verwalten“ → `page=two_factor` mit Erklärung + QR.
- Einrichtung abschließen → Backup-Codes auf 2FA-Seite notieren.
- Logout → Login → Passwort → `step=2fa` → Handoff.

## Wichtige Dateien

| Bereich | Dateien |
|---------|---------|
| Auth / 2FA | `pages/two_factor.php`, `includes/two_factor.php`, `includes/two_factor_handlers.php`, `bridge_auth.php`, `bridge_auth_2fa.php`, `pages/login.php` |
| Account | `pages/account.php` (Link nur) |
| Security | `includes/authz.php`, `dbScripts/03_db_init_mongo_roles.php`, `dbScripts/11_db_init_users_two_factor.php` |
| Doku | `docs/security_roadmap.md`, `README.md`, `AGENTS.md` |

## Referenz

`docs/security_roadmap.md` — Schritte 1–5 + 2FA umgesetzt; offen: Re-Auth (6–7), Deployment, UI-Backlog.
