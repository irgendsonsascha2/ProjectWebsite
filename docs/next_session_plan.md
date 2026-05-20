# Plan für neue Session (Stand 2026-05-20)

Kurzüberblick zum Weitermachen — Zielbild, erledigt, offen, Test.

## Zielbild Auth (bestätigt)

| Thema | Entscheidung |
|--------|----------------|
| Laravel | **Vorerst nur** Login (`bridge_auth.php`), Registrierung mit Invite-Code (`bridge_register.php`), Passwort-Reset. **Kein** Laravel `/verify-email` für die klassische Site. |
| Registrierung A (Anfrage) | `pages/register.php` → E-Mail-Link `verify_registration_request` → Admin `registration_requests` → Code per Mail → Konto mit Code. |
| Registrierung B (Admin) | `pages/admin/invite_codes.php` → Code → `pages/register.php` / `bridge_register.php`. |
| E-Mail nach Login | **Kein** Zwang. Invite-Registrierung setzt `email_verified_at` beim Anlegen. |
| 2FA | **Optional**, TOTP in **klassischer PHP** (`pages/account.php`), nicht Laravel. Backup-Codes wenn 2FA aktiv. **Kein** E-Mail-2FA beim Login. |

## Erledigt (Repo, lokal)

### Sprint 1
- CSRF Legacy-POST, Session-Cookies, Handoff `session_regenerate_id`, POST-Logout, `APP_ALLOW_DEV_DB_DEFAULTS`, Rate-Limiter Proxy.

### Sprint 2
- Mongo RBAC (`03_db_init_mongo_roles.php`): App-Rollen ohne `users`/`registration_codes`; `registration_code_requests` für Gäste; `user_db.php`; Projekt-Indizes.

### Sprint 3 + Nachzüge
- `includes/authz.php`: Login/Rechte, Projekt/Kommentar-Guards, Session-Sync aus DB.
- **Kein** Laravel-E-Mail-Gate mehr (`authz_apply_email_verification_gate` deaktiviert; Aufruf aus `bootstrap.php` entfernt).
- `RegisterInvitedUser`: `email_verified_at` bei Code-Registrierung.
- Admin: CSRF auf `invite_codes` / `registration_requests`; CSRF-Fehler → gleiche Admin-URL (nicht Startseite).
- `legacy_index_url()` für Admin-Redirects (früherer Fix).

**Branch:** `main`, typisch **8+ Commits** vor `origin/main` (lokal nicht gepusht).

## Offen (priorisiert)

### P1 — Auth/Registrierung konsistent
1. **`email_verified_at` bei Anfrage-Flow:** Beim Register mit Code prüfen, ob E-Mail zu einer **verifizierten** `registration_code_requests`-Zeile passt → dann `email_verified_at` setzen (zusätzlich zu reinem Invite-Code).
2. **Admin-POSTs:** Weitere Formulare in `pages/admin/*` mit `csrf_field()` (roles, permissions, settings, home_profile, legal_page_edit) — gleiches Muster wie `invite_codes`.
3. **Laravel `User`:** `MustVerifyEmail` optional entfernen oder dokumentieren, dass nur Laravel-Dashboard betroffen wäre (Legacy nutzt es nicht).

### P2 — Optional 2FA (früher „Sprint 4“)
- User-Felder: `two_factor_enabled`, `two_factor_totp_secret`, `two_factor_backup_codes`, …
- Account-UI: TOTP einrichten/deaktivieren, Backup-Codes.
- Login: nach Passwort optional TOTP-Challenge (`bridge_auth` / Handoff).
- DB-Skript für Felder/Indizes.

### P3 — Betrieb / Mongo
- `03_db_init_mongo_roles.php` nach RBAC-Änderung im Admin ausführen; `ADMIN_DB_URI` im laufenden PHP-Prozess verifizieren.
- `registration_codes`-Insert nur mit Admin-DB-User (RBAC).
- Nach `db_init_master`: ggf. neu einloggen (Session `user_id`).

### P4 — Später
- Admin Re-Auth vor destruktiven DB-Aktionen.
- Deployment Docker/DynDNS (nicht im Repo).
- Frontend/CSS-Aufräumen (`current_status.md` UI-Backlog).

## Lokaler Test (kurz)

```bash
# Root
php -S 127.0.0.1:8080 -t .
# Laravel
cd laravel && php artisan serve --host=127.0.0.1 --port=8000
# Mail (optional)
docker run --rm -p 1025:1025 -p 8025:8025 mailhog/mailhog
```

`.env.local`: `APP_ALLOW_DEV_DB_DEFAULTS=1`, Mongo-URIs, `MAIL_SMTP_*`, `LEGACY_SITE_URL=http://127.0.0.1:8080` in `laravel/.env`.

**Checks:**
- Admin → Einladungscode generieren (bleibt auf `invite_codes.php`, Code sichtbar).
- Login mit Code-Nutzer ohne Laravel-Verify-Hinweis.
- Registrierung mit gültigem Code → Handoff → Home.

## Wichtige Dateien

| Bereich | Dateien |
|---------|---------|
| Auth | `includes/authz.php`, `includes/bootstrap.php`, `bridge_auth.php`, `bridge_register.php`, `laravel_handoff.php` |
| Registrierung | `pages/register.php`, `pages/verify_registration_request.php`, `pages/admin/registration_requests.php`, `pages/admin/invite_codes.php`, `laravel/app/Services/RegisterInvitedUser.php` |
| Security | `includes/csrf.php`, `includes/rate_limit.php`, `dbScripts/03_db_init_mongo_roles.php` |
| Doku | `docs/security_roadmap.md`, `docs/current_status.md`, `AGENTS.md` |

## Referenz

Details und nummerierte Roadmap-Schritte: `docs/security_roadmap.md`.
