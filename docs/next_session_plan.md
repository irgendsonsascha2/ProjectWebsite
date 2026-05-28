# Plan für neue Session (Stand 2026-05-28)

Kurzüberblick — **offen zuerst**, erledigt komprimiert. Tests: [smoke_test.md](smoke_test.md).

## Offen / nächste Schritte

- **Server-Deploy:** Entwurf in [deployment.md](deployment.md); vor Go-Live [smoke_test.md](smoke_test.md) und `make deploy-check` / `make prod-env-check`.
- **Mongo nach Passwort-Änderung:** `03_db_init_mongo_roles.php` im Admin (Checkbox „`.env.local` aktualisieren“ optional); PHP-Server neu starten; nach `db_init_master` neu einloggen.
- **Optional:** UI-Backlog (Admin-`onclick` → `data-confirm` für strengere CSP); Mini-CMS — siehe [current_status.md](current_status.md).

## Zielbild Auth (unverändert)

| Thema | Entscheidung |
|--------|----------------|
| Laravel | Login (`bridge_auth.php`), Registrierung mit Invite (`bridge_register.php`), Passwort-Reset. **Kein** `/verify-email` für die klassische Site. |
| Registrierung A | Anfrage → E-Mail-Verifikation → Admin `registration_requests` → Code per Mail. |
| Registrierung B | Admin `invite_codes` → Code → `register` / `bridge_register`. |
| 2FA | Optional TOTP in klassischer PHP (`pages/two_factor`); Login `step=2fa` wenn aktiv. |

## Erledigt (Zusammenfassung)

- **Session/CSRF:** Legacy-POST-CSRF, Cookie-Flags, Handoff `session_regenerate`, POST-Logout, Rate-Limiter mit Proxy-IPs.
- **Mongo RBAC:** App-Rollen ohne direkten Zugriff auf `users`/`registration_codes`; `user_db.php`; Projekt-Indizes; Handoff nur über Admin-URI.
- **AuthZ:** `includes/authz.php`, kein Laravel-E-Mail-Gate, `email_verified_at` bei Invite/Anfrage-Flow.
- **2FA & Moderation:** TOTP optional; Admin-Nutzer Timeout/Ban; Brücken + Session-Sync.
- **Admin Re-Auth:** 15-Min.-Fenster (`admin_reauth.php`) für DB-Skripte, Codes, Nutzer, Einstellungen.
- **Infrastruktur:** Docker/MailHog, Deploy-Status, `schema_migrations`, `make deploy-check`, `.env.production.example`.
- **Security-Pass:** Medien-Proxy, CSP, Rate-Limits, `15_db_init_security_baseline.php`, Handoff-Nonce, `registration_codes.php` — Checkliste [security_local_checklist.md](security_local_checklist.md).

## Lokaler Test (kurz)

```bash
make php          # oder ./serve-php.sh
make laravel
make frontend-build
```

Siehe [smoke_test.md](smoke_test.md) (Login/Handoff, Registrierung, Passwort-Reset, Admin Re-Auth, 2FA/Moderation).

## Wichtige Dateien

| Bereich | Dateien |
|---------|---------|
| Auth / 2FA | `bridge_auth.php`, `bridge_auth_2fa.php`, `pages/two_factor.php`, `pages/login.php` |
| Admin | `includes/admin_reauth.php`, `pages/admin/users.php`, `pages/admin/db_scripts.php` |
| Doku | [security_roadmap.md](security_roadmap.md), [README.md](../README.md) |
