# AGENTS.md

Diese Datei beschreibt, wie KI-Agenten in diesem Repository arbeiten sollen.

## Projektziel

Dieses Projekt ist eine kleine private Social-Media-Website. Ziel ist eine persönliche Website, auf der eigene Projekte als Bilder oder Videos veröffentlicht und präsentiert werden können.

Die Plattform ist nicht als öffentliches Massen-Netzwerk gedacht, sondern als privater Raum mit kontrolliertem Zugang. Nutzer kommen über Einladungscodes in das System und erhalten abhängig von ihrer Rolle unterschiedliche Rechte.

## Fachlicher Fokus

Wichtige Kernfunktionen des Projekts:

- eigene Projekte als Posts anlegen
- Bilder und Videos zu Projekten hochladen
- Projekte in einer Grid-Ansicht durchsuchen
- Projekte im Detail mit Medien ansehen
- Likes und Dislikes pro Medium vergeben
- Kommentare und Antworten schreiben
- Zugriff über Rollen und Berechtigungen steuern
- Registrierung nur über Invite-/Registrierungscodes erlauben

## Technischer Rahmen

- PHP-Anwendung ohne Framework
- Einstiegspunkt: `index.php`
- gemeinsame Initialisierung: `includes/bootstrap.php`
- Seitenlogik in `pages/`
- Admin-Funktionen in `pages/admin/`
- MongoDB als Datenbank
- Medien lokal unter `content/images` und `content/videos`
- **parallel:** Verzeichnis **`laravel/`** — Laravel (MongoDB über `mongodb/laravel-mongodb`) übernimmt **Auth-Logik** (Login/Register/Passwort/Verifizierung); die klassische Website bleibt Router + Seiten + `$_SESSION` nach **Handoff**.

## Lokaler Start: Server, Website, Styling (React/Vite)

Damit die klassische PHP-Seite sichtbares Styling (Tailwind/React-Bundle aus `react-dist/`, Einbindung über `includes/vite_assets.php`) bekommt:

**Empfohlen: nur PHP-Dev-Server + einmaliger Frontend-Build**

1. Eine gültige **`.env.local`** im **Projektroot** (lokal, nicht committen) mit den MongoDB-URIs, siehe `README.md` (Vorlage: `.env.local.example`).
2. **Mail lokal (empfohlen):** `make services-up`, dann PHP + Laravel beide auf MailHog — `docs/local_mail_setup.md`, `make mailhog-check`.
3. **Frontend bauen** (erzeugt `react-dist/` inkl. `manifest.json` und CSS/JS-Hashes; der Ordner ist **kein** Git-Bestandteil — nach Klon o. ä. zwingend bauen):
   - `cd frontend && npm install && npm run build`
4. **PHP eingebauten Server** im **Projektroot** starten (mit Medien-Router):
   - `make php` oder `./serve-php.sh` (empfohlen, inkl. `router.php` für geschützte `content/`-URLs)
   - alternativ: `php -S 127.0.0.1:8080 -t . router.php`
5. Im Browser öffnen: `http://127.0.0.1:8080/` (Host einheitlich **127.0.0.1** verwenden, nicht wahlweise `localhost` mischen, siehe Doku)

**Optional: HMR (Vite-Dev) für schnellere Frontend-Iteration**

1. In **`.env.local`**: `VITE_HMR=1` und `VITE_DEV_SERVER_URL=http://127.0.0.1:5173` (Port wie Vite; Variable muss im PHP-Prozess landen, dafür reicht die Datei, sie wird über `includes/db.php` geladen).
2. **Terminals:** zuerst Vite, dann PHP:
   - `cd frontend && npm run dev -- --host 127.0.0.1 --port 5173`
   - im Projektroot: `php -S 127.0.0.1:8080 -t .`
3. Wenn HMR Probleme macht, HMR-Variablen in `.env.local` entfernen bzw. auskommentieren und mit **nur** `npm run build` (ohne laufendes Vite) arbeiten.

`includes/vite_assets.php` nutzt HMR **nur** mit `VITE_HMR=1` **und** `VITE_DEV_SERVER_URL` und wenn der PHP-Prozess Vite per Socket/HTTP erreicht und der `@vite/client` mit 200 antwortet; sonst Manifest. Bei aktivem HMR kommen **zusätzlich** die zuletzt gebauten **CSS-`<link>`-**Einträge aus `react-dist/.vite/manifest.json` (braucht also einen vorherigen `npm run build`). Fehlersuche: `README.md` → *Wenn kein Styling / keine React-Assets*; technischer Stand: `docs/current_status.md` (Abschnitt Vite-PHP-Brücke).

**Laravel-Auth-App (getrennt):** Eigener Start über `laravel/`, `php artisan serve` bzw. wie in `README.md` beschrieben; nicht mit dem obigen `php -S` verwechseln.

## Hybrid-Authentifizierung (Laravel)

- **`bridge_auth.php`** / **`bridge_register.php`** (Projektroot): laden Laravel, prüfen Credentials bzw. **`RegisterInvitedUser`**, danach Redirect über **`LegacySiteHandoff`** zu **`laravel_handoff.php`** (HMAC), dort wird dieselbe PHP-Session wie früher gesetzt.
- **`laravel_handoff.php`**: setzt `user_id`, Rolle, `permissions` aus Mongo; Konfiguration in **`laravel/.env`** (`HANDOFF_SECRET`, `LEGACY_SITE_URL`, optional `LEGACY_AFTER_LOGIN_PAGE`). Basis-URL der klassischen Site **inkl. Port**.
- **`includes/laravel_app_url.php`**: liest **`APP_URL`** aus `laravel/.env` für Links (z. B. Passwort vergessen auf Port 8000).
- **`includes/bootstrap.php`**: bei unvollständiger Session wird der Nutzer zur Reparatur mit **Admin-Mongo** aus `users` geladen (nicht nur rollenbeschränkte Connection), damit keine fälschliche Abmeldung entsteht.
- **`dbScripts/04_db_users_validator_allow_laravel.php`**: einmal auf bestehenden DBs ausführen, wenn Mongo „Document failed validation“ bei Laravel-Inserts meldet (`users`-Validator mit `additionalProperties`).
- Details, Setup und Tests: **`README.md`** Abschnitt zu Laravel und Brücken.

## Wichtige Dateien

- `index.php`
  - einfacher Router über `?page=...` (Standardseite: `home`)
- `includes/bootstrap.php`
  - Session, MongoDB-Verbindung, Rollen/Rechte, Upload-Helfer
- `includes/request.php` / `includes/input_validate.php`
  - Whitelist-Validierung für HTTP-Eingaben (siehe `docs/input_validation.md`)
- `pages/login.php` / `pages/register.php`
  - Login und Registrierung (Brücken `bridge_auth.php`, `bridge_register.php`)
- `pages/account.php`
  - Eingeloggt-Dashboard (2FA-Link, Logout)
- `pages/two_factor.php`
  - Optionales TOTP-2FA (Einrichtung, QR, Backup-Codes)
- `pages/home.php`
  - öffentliche Startseite mit Kurzprofil
- `pages/project_grid.php`
  - Projektübersicht
- `pages/project_detail.php`
  - Detailansicht, Likes, Dislikes, Kommentare
- `pages/create_project.php`
  - Projektanlage, Entwürfe, Medien-Uploads
- `pages/edit_project.php`
  - Projektbearbeitung
- `pages/admin/index.php`
  - Admin-Dashboard (DB-Konfig-Überblick, Metriken)
- `pages/admin/db_scripts.php`
  - Ausführung der destruktiven `dbScripts/`-Skripte
- `pages/admin/deploy_status.php` / `includes/deploy_status.php`
  - Read-only Deploy-Checkliste; CLI: `make deploy-check`, `make prod-env-check`
- `scripts/deploy-check.php`, `scripts/ensure-db-baseline.php`, `scripts/run-db-script.php`
  - Deploy-Prüfungen und idempotente DB-Skripte 14/15/16 ohne Admin-UI
- `.env.production.example`, `deploy/nginx.example.conf`, `deploy/Caddyfile.example`
  - Vorlagen für Server-Env und Reverse-Proxy (siehe `docs/deployment.md`)
- `router.php` / `media.php` / `includes/media_serve.php`
  - Geschützte Auslieferung von `content/images`, `content/videos` und `content/tmp` (Entwurfs-/Bearbeitungs-Medien)
- `includes/admin_reauth.php`
  - 15-Min.-Admin-Bestätigung (Passwort + optional TOTP); UI über Dialoge (`admin_reauth_confirm_dialog` usw.)
- `dbScripts/15_db_init_security_baseline.php`
  - Idempotente Security-Indizes (Handoff, projects, users-Moderation); beliebig im Admin wiederholbar
- `pages/admin/_layout.php`
  - gemeinsames Admin-Layout (Nav, Vite-Assets, Theme); lädt `js/theme-bootstrap.js`, `js/admin-dialogs.js` (am Seitenende)
- `js/theme-bootstrap.js`, `js/admin-dialogs.js`, `js/admin-*.js`
  - Theme FOUC-Schutz; Admin-Dialoge nach DOM (nicht im `<head>`); seiten-spezifisches Admin-JS
- `pages/admin/roles.php`
  - Rollenverwaltung
- `pages/admin/permissions.php`
  - Berechtigungsverwaltung
- `dbScripts/`
  - destruktive Initialisierung und Reset von Collections
- `README.md`
  - zentrale Projektdokumentation für Menschen
- `laravel/`
  - Laravel-App (Auth); Konfiguration nur über **`laravel/.env`** (nicht ins Repo committen); **`laravel/.env.example`** als Vorlage
- `bridge_auth.php`, `bridge_register.php`, `laravel_handoff.php`
  - Verbindung klassische PHP-Session ↔ Laravel-Auth

## Arbeitsregeln für Agenten

- Vor Änderungen immer zuerst den aktuellen Bestand lesen.
- Bestehende Architektur respektieren und keine unnötigen Umbauten durchführen.
- Kleine, gezielte Änderungen sind großen Refactorings vorzuziehen.
- Änderungen müssen zum tatsächlichen Projektziel passen: private Plattform zum Präsentieren eigener Projekte.
- Rollen, Berechtigungen, Invite-System, Uploads und DB-Skripte sind sensible Bereiche und müssen vorsichtig geändert werden.
- Vorhandene Nutzerflüsse nicht ohne guten Grund brechen.
- Keine stillen Verhaltensänderungen einführen.

## Dokumentationspflicht

Jede relevante Änderung am Verhalten, an Features, an Rollen/Berechtigungen, an Setup-Schritten, an Datenbankskripten oder an der Projektstruktur muss auch in `README.md` dokumentiert werden.

Reiner Code reicht nicht aus, wenn sich daraus neues Verhalten, neue Voraussetzungen oder geänderte Bedienung ergibt. Dokumentation und Code gehören in denselben Arbeitsschritt.

Wenn eine Änderung tiefergehende technische Erklärung braucht, soll zusätzlich eine Datei unter `docs/` angelegt oder erweitert werden.

## Änderungsregeln

- Keine destruktiven Datenbank-Resets ohne ausdrückliche Freigabe ausführen.
- Keine bestehenden Benutzerinhalte ohne klare Anforderung löschen.
- Keine Zugangsdaten, Secrets oder Umgebungswerte neu hart codieren.
- Änderungen an Rollen und Berechtigungen müssen konsistent in UI, Logik und Dokumentation sein.
- Änderungen an Upload-Logik müssen Dateitypen, Limits und Speicherorte berücksichtigen.
- Änderungen an Interaktionen müssen auf Projektebene und Medienebene korrekt bleiben.
- Neue nummerierte Dateien in `dbScripts/` sind Teil der Master-Initialisierung und müssen so geschrieben werden, dass sie bei Ausführung von `dbScripts/db_init_master.php` mitlaufen können.
- Sensible Seed-Daten wie initiale Admin-Credentials oder MongoDB-Rollenpasswörter sollen nicht hart im Code stehen, wenn sie beim Ausführen sicher abgefragt werden können.

### Eingabevalidierung (Pflicht bei User-Input)

Neue **`pages/`**, POST-Handler und **`dbScripts/`**, die Formular- oder Request-Daten verarbeiten, **müssen** Whitelist-Validierung nutzen — **keine** rohen `$_POST`/`$_GET`-Strings in Mongo-Queries, Dateipfaden oder Shell.

| Art | Verwenden |
|-----|-----------|
| Skalare, Länge, ObjectId | [`includes/request.php`](includes/request.php) (`req_post_string`, `req_get_objectid`, …) |
| Text, E-Mail, Enums, Slugs, Script-Namen | [`includes/input_validate.php`](includes/input_validate.php) |
| Bild/Video-Upload | [`validate_media_upload()`](includes/bootstrap.php) in `includes/bootstrap.php` |
| dbScripts-Dialog (Passwörter, Parameter) | [`dbScripts/_script_input_helpers.php`](dbScripts/_script_input_helpers.php) (`db_script_resolve_password`, …) |

Prinzip: **Whitelist** (erlaubte Zeichen/Längen/Werte), nicht Blacklist verbotener Symbole. Ausgabe weiter escapen (`htmlspecialchars`). Details: [`docs/input_validation.md`](docs/input_validation.md).

## Qualitätsmaßstab

Jede Änderung soll prüfen, ob sie:

- das Projektziel unterstützt
- bestehende Rollenrechte respektiert
- das Invite-basierte Privatheitsmodell beibehält
- die Medienlogik für Bilder und Videos nicht beschädigt
- in `README.md` nachvollziehbar dokumentiert ist

## Erwartetes Ergebnis nach Änderungen

Nach einer Aufgabe sollte ein Agent mindestens benennen:

- was geändert wurde
- welche Dateien betroffen sind
- ob die `README.md` angepasst wurde
- welche Risiken, Lücken oder ungetesteten Punkte bleiben
