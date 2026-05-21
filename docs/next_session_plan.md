# Plan für neue Session (Stand 2026-05-21)

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

### Sprint 5 — Nutzer-Moderation (Admin)
- `includes/user_moderation.php`: Timeout/Ban, Gründe, öffentliche Meldung, Admin-Schutz.
- **`pages/admin/users.php`**: Suche, Sperre setzen/aufheben, „Grund anzeigen“.
- Durchsetzung: `bridge_auth.php`, `bridge_auth_2fa.php`, `laravel_handoff.php`, `includes/authz.php` (Session-Sync), Laravel `LoginRequest`.
- `dbScripts/13_db_init_users_moderation.php` (sparse Index).
- Einladungscodes: Schreibzugriff auf `registration_codes` nur über **`get_admin_mongo_connection()`** (Account-Generierung angepasst).

**Branch:** `main`, viele Commits vor `origin/main` (lokal nicht gepusht).

## Nächste Session — Start hier (P4)

### P3 — Betrieb / Mongo (lokal geprüft 2026-05-21)
- ✅ `ADMIN_DB_URI` / Admin-Ping OK; Custom-Rollen `viewerRole`, `communityMemberRole`, `contentManagerRole` vorhanden.
- ✅ `11_db_init_users_two_factor.php` und `13_db_init_users_moderation.php` per CLI erfolgreich.
- **Manuell im Admin**, wenn Passwörter geändert wurden: `03_db_init_mongo_roles.php` ausführen (Checkbox „`.env.local` aktualisieren“ optional).
- Nach **`db_init_master`**: neu einloggen (Session `user_id` kann ungültig sein).

### P4 — Admin Re-Auth (umgesetzt 2026-05-21)
- ✅ `includes/admin_reauth.php`: 15-Min.-Fenster nach Passwort (+ TOTP wenn 2FA aktiv).
- ✅ `pages/admin/db_scripts.php`: CSRF-Prüfung, Re-Auth vor `run_script`, Felder in Ausführen-Dialogen.

### P5 — Infrastruktur & UI (2026-05-21, Teil)
- ✅ `docker-compose.yml` + `make services-up` (MongoDB, MailHog).
- ✅ `docs/deployment.md` (lokal + Entwurf Server/DynDNS).
- ✅ Copy-UI Admin Einladungscodes (`CopyField` Abstand, `admin.css`).
- **Nächstes:** Server-Deploy umsetzen (wenn entschieden); UI-Backlog Glass/Responsive/CSS-Split (`current_status.md`).

### P6 — Aufräumen & Security-Pass (2026-05-21)
- ✅ Toter Code (theme-toggle.js, account Code-Generator, authz-Stubs, React Card).
- ✅ `registration_codes.php`, Handoff-Einmal-Nonce (`14_db_init_handoff_tokens.php`), SVG blockiert, Security-Header, Rate-Limits.
- ✅ Laravel slim (kein Verify-Email/Dashboard/Profil im Hybrid).
- ✅ CSP-Hotfix + Admin-JS: `js/admin-dialogs.js`, `js/theme-bootstrap.js` (DB-Skripte-Dialoge wieder nutzbar).
- ✅ Experiment `?reactlb=1` / `MediaLightbox.tsx` entfernt.
- ✅ Symfony-CVEs in `laravel/` per `composer update` behoben.
- ✅ Admin-JS vollständig externalisiert (`admin-roles.js`, `admin-users.js`, `admin-legal-sections.js`); kaputtes `</html>` in `roles.php` entfernt.
- ✅ Admin-Dialoge: `admin-dialogs.js` / `csrf-forms.js` am Ende des Body (nicht im `<head>`).
- ✅ Gemeinsames `js/theme-bootstrap.js` (Site + Admin); `csrf-forms.js` mit `DOMContentLoaded`.
- ✅ Glass-Utility `.glass-surface` / `.glass-pill` / `.glass-bar` in `frontend/src/styles/components/glass.css`; Overlays Grid/Detail/Create-Edit zentral.
- ✅ Responsive-Pass Admin (≤40rem): volle Breite Container, Vollbild-Dialoge, Script-Liste gestapelt, Card-Actions nicht absolut.
- ✅ Projekt-Detail: weniger `padding-right` auf Hover-Kommentaren bei schmalen Viewports.
- ✅ Inline-Skripte nach `js/`; CSP `script-src 'self'`; CSRF nur Meta-Tag.
- ✅ Responsive Lightbox/FABs/Account; CopyField-Abstände Account.
- **Optional:** Admin-`onclick` entfernen (`script-src-attr` ohne `unsafe-inline`).

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

**Checks (Moderation):**
- Admin → **Nutzer** → Timeout/Ban setzen → Logout des Nutzers → Login zeigt Sperr-Meldung (ggf. ohne Grund, wenn `show_reason` aus).
- Sperre aufheben → Login wieder möglich.

**Checks (DB-Skripte / Re-Auth):**
- Admin → **DB-Skripte** → Skript ausführen: Passwort (+ TOTP wenn 2FA) → Erfolg; Banner „Bestätigung aktiv“ (~15 Min.).
- Zweites Skript innerhalb des Fensters ohne erneute Passwort-Eingabe.
- Nach Ablauf (oder neuer Tab nach Logout) wieder Passwort nötig; falsches Passwort blockiert Ausführung.

**Checks (Admin mobil / Dialoge):**
- Viewport ≤40rem oder DevTools: DB-Skripte, Rollen, Nutzer — Dialoge öffnen/schließen; kein horizontaler Scroll im Dialog.
- Hart neu laden nach `npm run build`.

## Wichtige Dateien

| Bereich | Dateien |
|---------|---------|
| Auth / 2FA | `pages/two_factor.php`, `includes/two_factor.php`, `includes/two_factor_handlers.php`, `bridge_auth.php`, `bridge_auth_2fa.php`, `pages/login.php` |
| Moderation | `pages/admin/users.php`, `includes/user_moderation.php`, `dbScripts/13_db_init_users_moderation.php` |
| Account | `pages/account.php` (Link 2FA; Code-Generierung über Admin-DB) |
| Security | `includes/authz.php`, `includes/admin_reauth.php`, `dbScripts/03_db_init_mongo_roles.php`, `dbScripts/11_db_init_users_two_factor.php` |
| Doku | `docs/security_roadmap.md`, `README.md`, `AGENTS.md` |

## Referenz

`docs/security_roadmap.md` — Schritte 1–7 umgesetzt. `docs/deployment.md` — lokal Docker; Server offen.
