# Sicherheit — Überblick

Kurzdokumentation zum Sicherheitsmodell der klassischen PHP-Site und der Laravel-Auth-Brücke. Detaillierte Roadmap: [security_roadmap.md](security_roadmap.md). Lokale Iteration und manuelle Tests: [security_local_checklist.md](security_local_checklist.md).

## Zugang und Identität

- **Privat:** Registrierung nur mit Invite-Code oder nach Admin-Freigabe einer Registrierungsanfrage.
- **Login:** Laravel (`bridge_auth.php`) → optional TOTP (`bridge_auth_2fa.php`) → HMAC-Handoff (`laravel_handoff.php`) mit **Einmal-Nonce** (`handoff_tokens`, Index `dbScripts/14_db_init_handoff_tokens.php`).
- **Session:** `HttpOnly`, `SameSite=Strict`, `Secure` bei HTTPS; Regenerierung nach Handoff.
- **Moderation:** Timeout/Ban auf `users`; Durchsetzung bei Login, Handoff und Session-Sync.

## Autorisierung

- **Rollen + Permissions** in Mongo; `can()` / `includes/authz.php` für Projekt-, Kommentar- und Medienzugriff.
- **Entwürfe:** nur Autor sieht Projekt und zugehörige Medien (siehe Medien-Proxy).
- **Admin:** sensible Seiten verlangen Rolle `admin` und Permission `manage_users` (Nutzer, Rollen, Berechtigungen, Registrierungsanfragen). DB-Skripte nur Rolle `admin`.

## Medien

- Dateien liegen unter `content/images`, `content/videos` und (während Bearbeitung) `content/tmp/`.
- **Auslieferung:** über `router.php` → `media.php` → `includes/media_serve.php` (nicht mehr ungeschützt direkt vom Webroot).
- **Veröffentlichte Medien:** Zugriff über `authz_can_view_project()` (Entwürfe nur Autor).
- **Tmp-Medien** (`content/tmp/{userId}/{projectId}/…`): nur eingeloggt und `authz_can_edit_project()` für das Projekt.
- **Apache/nginx:** `content/.htaccess` verweigert direkten Zugriff; Auslieferung nur über die App (Rewrite auf `media.php` o. ä.).
- **Startseiten-Portrait** in `site_pages` (`home_profile`) ist öffentlich, wenn als `content/images/…` gespeichert.

Lokal: `make php` oder `./serve-php.sh` (beide nutzen `router.php`).

## Logs

- Anwendungs- und Rate-Limit-Dateien unter `logs/` (gitignored).
- **Nicht** öffentlich: `logs/.htaccess` (Apache) und Block in `router.php` (PHP-Dev-Server).
- Produktion: Document Root so wählen, dass `logs/` nicht erreichbar ist, oder explizit verweigern.

## HTTP-Härtung

- **Security-Header:** `includes/security_headers.php` (Legacy), Laravel `SecurityHeaders`-Middleware.
- **CSP:** `script-src 'self'`; Bestätigungsdialoge über `data-confirm-submit` + `js/csrf-forms.js` (kein `script-src-attr 'unsafe-inline'`).
- **CSRF:** Legacy-POSTs über `includes/csrf.php` / Meta-Tag + `js/csrf-forms.js`.

## Rate-Limits (Auszug)

| Bucket | Limit | Bereich |
|--------|-------|---------|
| `login` / `register` | Laravel BridgeRateLimiter | Brücken |
| `comment_post` | 30 / 10 Min. | Kommentare |
| `handoff_fail` | 20 / 15 Min. | Fehlgeschlagene Handoffs |
| `registration_code_request` | 5 / Stunde | Code-Anfragen |
| `interaction_post` | 60 / 10 Min. | Likes/Dislikes |
| `media_upload` | 30 / 10 Min. pro User | Medien-Upload |

## Admin — Re-Auth

- **15 Minuten** gültiges Fenster nach Passwort (+ TOTP wenn 2FA aktiv): `includes/admin_reauth.php`.
- Gilt für Rollen `admin` und `content_manager` (Passwort aus `users`).
- Pflicht vor: DB-Skript-Ausführung, Nutzer-Moderation, Rolle/Berechtigung löschen/ändern, Registrierungsfreigabe, **Einladungscode erzeugen**, **Startseite**, **Rechtstexte**, **Site-Einstellungen** (nur `admin`).
- **UI:** Passwort/TOTP erscheinen nur im **Bestätigungs-Dialog** (oder Kurzform bei aktivem 15-Min.-Fenster), nicht dauerhaft in Seitenformularen. Hilfen: `admin_reauth_primary_button()`, `admin_reauth_confirm_dialog()`, `admin_reauth_dialog_body()`; Registrierungsfreigabe: `js/admin-reauth-approve.js`.

## Uploads

- Kein SVG; Extension-Whitelist; Bilder mit `getimagesize`; Videos mit MIME/Header-Check.
- Größen- und Auflösungslimits in `includes/bootstrap.php` / `includes/site_settings.php`.

## Datenbank

- Getrennte Mongo-URIs pro Rolle; Admin-Skripte nur über `ADMIN_DB_URI`.
- `dbScripts/` nicht direkt per URL; Laufzeit-Guard + Admin-Pfad.
- **`15_db_init_security_baseline.php`:** idempotent Handoff- + Projekt- + Moderation-Indizes (beliebig wiederholbar, auch in `db_init_master`).

## Entwicklung vs. Produktion

- Lokal: MailHog, `APP_ALLOW_DEV_DB_DEFAULTS` nur bewusst für Tests.
- Produktion: siehe [deployment.md](deployment.md) (TLS, keine Dev-Defaults, Mongo mit Auth).
