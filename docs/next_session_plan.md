# Plan für neue Session (Stand 2026-05-20)

Kurzüberblick zum Weitermachen — Zielbild, erledigt, offen, Test.

## Zielbild Auth (bestätigt)

| Thema | Entscheidung |
|--------|----------------|
| Laravel | **Vorerst nur** Login (`bridge_auth.php`), Registrierung mit Invite-Code (`bridge_register.php`), Passwort-Reset. **Kein** Laravel `/verify-email` für die klassische Site. |
| Registrierung A (Anfrage) | `pages/register.php` → E-Mail-Link `verify_registration_request` → Admin `registration_requests` → Code per Mail → Konto mit Code. |
| Registrierung B (Admin) | `pages/admin/invite_codes.php` → Code → `pages/register.php` / `bridge_register.php`. |
| E-Mail nach Login | **Kein** Zwang. `email_verified_at` bei Registrierung (Admin-Code oder passende verifizierte Anfrage). |
| 2FA | **Optional**, TOTP in **klassischer PHP** (`pages/account.php`), nicht Laravel. Backup-Codes wenn 2FA aktiv. **Kein** E-Mail-2FA beim Login. |

## Erledigt (Repo, lokal)

### Sprint 1 — Session & CSRF
- CSRF Legacy-POST, Session-Cookies, Handoff `session_regenerate_id`, POST-Logout, `APP_ALLOW_DEV_DB_DEFAULTS`, Rate-Limiter Proxy.

### Sprint 2 — Mongo RBAC
- `03_db_init_mongo_roles.php`: App-Rollen ohne `users`/`registration_codes`; `registration_code_requests` für Gäste; `user_db.php`; Projekt-Indizes.

### Sprint 3 — AuthZ & Registrierung
- `includes/authz.php`, kein Laravel-E-Mail-Gate, `resolveEmailVerifiedAt()`, Admin-CSRF vollständig, `MustVerifyEmail` entfernt.

### Sprint 4 — Optionales TOTP-2FA
- `includes/two_factor.php`, `bridge_auth.php` / `bridge_auth_2fa.php`, `pages/login.php` (Schritt 2FA), `pages/account.php` (Setup/Backup/Deaktivieren).
- `dbScripts/11_db_init_users_two_factor.php` (Doku + sparse Index).

**Branch:** `main`, **13+ Commits** vor `origin/main` (lokal nicht gepusht).

## Nächste Session — Start hier (P3)

### P3 — Betrieb / Mongo
- `03_db_init_mongo_roles.php` nach RBAC-Änderung im Admin ausführen; `ADMIN_DB_URI` prüfen.
- `registration_codes`-Insert nur mit Admin-DB-User (RBAC).
- Nach `db_init_master`: ggf. neu einloggen (Session `user_id`).
- Optional einmal `11_db_init_users_two_factor.php` (Index).

### P4 — Später
- Admin Re-Auth vor destruktiven DB-Aktionen (`db_scripts.php`).
- Deployment Docker/DynDNS.
- Frontend/CSS (`current_status.md` UI-Backlog).

## Lokaler Test (kurz)

```bash
php -S 127.0.0.1:8080 -t .
cd laravel && php artisan serve --host=127.0.0.1 --port=8000
```

**Checks (Auth + 2FA):**
- Login ohne 2FA → Handoff → Home.
- Account → 2FA einrichten → Code bestätigen → Backup-Codes notieren.
- Logout → Login → TOTP → Handoff.
- Backup-Code einmalig; zweiter Versuch mit gleichem Code scheitert.

## Wichtige Dateien

| Bereich | Dateien |
|---------|---------|
| Auth / 2FA | `includes/two_factor.php`, `bridge_auth.php`, `bridge_auth_2fa.php`, `pages/login.php`, `pages/account.php` |
| Registrierung | `laravel/app/Services/RegisterInvitedUser.php` |
| Security | `includes/authz.php`, `dbScripts/03_db_init_mongo_roles.php`, `dbScripts/11_db_init_users_two_factor.php` |
| Doku | `docs/security_roadmap.md`, `README.md` |

## Referenz

`docs/security_roadmap.md` — Schritte 1–5 und 4 (2FA) umgesetzt; offen: Re-Auth (6–7), Doku-Feinschliff (8).
