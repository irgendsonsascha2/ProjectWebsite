# ProjectWebsite

Kleines privates Social-Media-/Portfolio-Projekt auf Basis von PHP und MongoDB. Die Anwendung bietet ein invite-basiertes Account-System, rollenbasierte Berechtigungen, Projekt-Posts mit Bild-/Video-Galerie sowie Interaktionen wie Likes, Dislikes und Kommentare.

## Überblick

Die Website ist als einfache PHP-Anwendung ohne Framework aufgebaut. `index.php` dient als zentraler Router und lädt Seiten aus dem Verzeichnis `pages/`. Gemeinsame Initialisierung wie Session-Start, MongoDB-Verbindung, Rollen-/Rechteauflösung und Hilfsfunktionen liegen in `includes/bootstrap.php`.

Ohne Parameter `page` wird die Startseite (`pages/home.php`) mit Kurzvorstellung geladen; die Projektübersicht (`project_grid`) ist weiterhin unter `index.php?page=project_grid` erreichbar und in der Navigation als **Projekte** verlinkt. Beim Auffrischen unvollständiger Sessions lädt `includes/bootstrap.php` Nutzerdaten per **Admin-Mongo-Verbindung** aus `users`, damit eingeloggte Nutzer nicht fälschlich wie „abgemeldet“ wirken, wenn die rollenbasierte DB keine Leserechte auf `users` hat.

Test-Aufrufe für geschützte Bereiche (z. B. Admin-URLs) immer unter **derselben Basis-URL** wie beim Login nutzen (**`127.0.0.1` und `localhost` sind verschiedene Origins** — eigene Cookies). **Hybrid-Laravel-Auth** (Brücken, Handoff, Dateien im Projektroot) ist für Agenten und Kurzüberblick in **`AGENTS.md`** beschrieben. Das Profilfoto liegt unter `img/` als `portrait.jpg`, `portrait.png` oder `portrait.webp` (es wird die erste vorhandene Datei genutzt; ohne Datei siehe `img/placeholder.svg`). Der angezeigte Name unter dem Foto wird in `pages/home.php` über die Variable `$displayName` gesetzt.

Hauptfunktionen:

- Invite-basierte Registrierung über Registrierungscodes
- Login/Logout mit Rollen und Berechtigungen
- Projektübersicht im Grid
- Projektdetails mit Mediengalerie
- Erstellung und Bearbeitung von Projekten
- Entwurfslogik für neue Projekte
- Likes/Dislikes pro Medium
- Kommentare und Antworten pro Medium
- Admin-Bereich für Datenbankskripte, Rollen und Berechtigungen

## Technik

- PHP
- Composer
- MongoDB
- Paket: `mongodb/mongodb`
- Frontend mit serverseitig gerenderten PHP-Seiten, CSS und etwas Vanilla JavaScript
- Zusätzlich: **Vite + React** zum Bündeln/Laden des Stylesheets (Hybrid-Setup, PHP bleibt Router/Renderer)

`composer.json` enthält aktuell nur die MongoDB-PHP-Bibliothek als Abhängigkeit.

### Laravel (`laravel/`)

Parallel zur klassischen PHP-App liegt eine **Laravel-13-Anwendung** mit **MongoDB** (`mongodb/laravel-mongodb`) für **Auth-Brücken** und **Passwort-Reset**. Login/Registrierung laufen auf der klassischen Site (`pages/login.php`, `pages/register.php`); Laravel liefert Credentials-Prüfung und Handoff.

**Testprojekt — Kurzablauf (du):**

1. **MongoDB starten** und in `laravel/.env` **`MONGODB_URI`** / **`MONGODB_DATABASE`** setzen (Zugriff wie die alte App; für Registrierung Schreibrechte auf `users` und `registration_codes`).
2. **`APP_URL=http://127.0.0.1:8000`** (oder dein Port), zu `artisan serve` passend.
3. **Frontend einmal bauen:** `cd laravel && npm install && npm run build`
4. **Server:** `cd laravel && ./serve-test.sh` **oder** `composer serve-app`

**Im Repo bereits vorbereitet:** Auth-Flow (Login/Register/Profil), `resources/js/bootstrap.js`, `axios`, Migrationen mit Schon-vorhanden-Collections, PHPUnit-Anpassungen, `serve-test.sh`, Composer-Script **`serve-app`**.

**Login direkt auf der klassischen Website:** Das Formular auf **`pages/login.php`** sendet an **`bridge_auth.php`** (Projektroot). Dort wird das Passwort wie in Laravel geprüft; bei Erfolg folgt derselbe Sprung wie nach Laravel-Login über **`laravel_handoff.php`** (HMAC + Einmal-Nonce).

**Registrierung dort ebenfalls:** Das Register-Formular sendet an **`bridge_register.php`** (Projektroot). Dort läuft dieselbe Logik wie **`POST /register`** in Laravel (**`RegisterInvitedUser`** Service): Invite-Code, Validierung, Nutzer anlegen, danach wie beim Login der Handoff über **`laravel_handoff.php`** (automatisch eingeloggt auf der klassischen Site).

**Keine zweite Login-Maske auf Port 8000:** Ist **`LEGACY_SITE_URL`** in `laravel/.env` gesetzt, leiten **`GET /login`** und **`GET /register`** in Laravel auf **`index.php?page=login`** bzw. **`page=register`** um. Laravel behält **`forgot-password`** / **`reset-password`**; Verify-Email, Dashboard und Profil sind im Hybrid-Modus entfernt. Breeze-Login/Register (POST) nur, wenn **`LEGACY_SITE_URL` leer** ist.

**Nach Laravel-Login zur klassischen Website:** **`laravel_handoff.php`** validiert HMAC + **Einmal-Nonce** (`handoff_tokens`, Index: `dbScripts/14_db_init_handoff_tokens.php`), setzt `$_SESSION` und leitet auf **`index.php?page=…`** weiter (Standard: `home`, `LEGACY_AFTER_LOGIN_PAGE`). **`HANDOFF_SECRET`** und **`LEGACY_SITE_URL`** in **`laravel/.env`** erforderlich.

**Passwort vergessen / neues Passwort (Laravel):** Auf der **Anmeldeseite** (`index.php?page=login`) verweist der Link **Passwort vergessen** auf **`{APP_URL}/forgot-password`** (typ. `http://127.0.0.1:8000`, Wert aus `laravel/.env`). Der Link in der E-Mail setzt das Passwort in Laravel; **nach erfolgreichem Speichern** folgt derselbe **Handoff** wie nach Login, sofern `HANDOFF_SECRET` / `LEGACY_SITE_URL` gesetzt sind. Dafür muss die Collection/Tabelle für Reset-Tokens existieren: einmal **`cd laravel && php artisan migrate`** (u. a. `password_reset_tokens`). **E-Mail lokal:** empfohlen **MailHog** für PHP (`.env.local`) und Laravel (`MAIL_MAILER=smtp`) — ein Posteingang, siehe **`docs/local_mail_setup.md`** (`make services-up`, `make mailhog-check`). Alternative nur Laravel: `MAIL_MAILER=log` → Reset-Link in `laravel/storage/logs/laravel.log`, nicht in MailHog.

**Brücken (Rate-Limiting):** **`bridge_auth.php`** und **`bridge_register.php`** drosseln zu viele Anfragen pro IP (Laravel `RateLimiter`, **~10/60s** Login, **~5/60s** Registrierung) und leiten mit `?err=throttle` zur Login- bzw. Register-Seite um.

**Optionales TOTP-2FA (klassische Site):** Eigene Seite **`index.php?page=two_factor`** (nur eingeloggt) mit QR-Code und Verwaltung; **2FA einrichten** startet die Einrichtung dort automatisch (QR sofort). Erklärung am Account und auf der 2FA-Seite über **?** (Modal-Popup). Backup-Codes nach Einrichtung/Neuerzeugung per Button **Backup-Codes kopieren** (alle Codes zeilenweise in die Zwischenablage). Login mit 2FA: Passwort auf der Anmeldeseite, danach Code auf **`index.php?page=login&step=2fa`**. Nach Passwort-Login leitet **`bridge_auth.php`** bei aktivem 2FA auf **`index.php?page=login&step=2fa`**; die zweite Phase läuft über **`bridge_auth_2fa.php`**. Backup-Codes werden gehasht in Mongo gespeichert und sind einmalig nutzbar. Felder: `two_factor_*` auf `users` (optionaler Index: `dbScripts/11_db_init_users_two_factor.php`). QR-Bundle: `js/qrcode.bundle.js` (nach Änderung an `qrcode`: `cd frontend && npm run build:qrcode`).

**E-Mail-Nachweis:** Kein Login-Gate über Laravel `/verify-email`. Registrierung setzt `email_verified_at` über **`RegisterInvitedUser`** (Invite-Code / verifizierte Anfrage). E-Mail-Bestätigung vor Code-Vergabe: `verify_registration_request` + Admin (`docs/next_session_plan.md`).

**Sicherheit (Auszug):** Kein SVG-Upload; Medien nur über Auth-Proxy (`router.php` / `media.php`, inkl. Bearbeitungs-`content/tmp/`); `logs/` nicht öffentlich; Security-Header (CSP ohne `script-src-attr`); Rate-Limits (Kommentare, Handoff, Likes, Uploads); Admin-Re-Auth für sensible Aktionen (DB-Skripte, Nutzer, Codes, Startseite, Rechtstexte, Einstellungen); idempotentes `dbScripts/15_db_init_security_baseline.php`. Details: `docs/security.md`, Roadmap `docs/security_roadmap.md`, lokale Tests `docs/security_local_checklist.md`.

**Wichtig beim Testen:** Laravel (`php artisan serve`, z. B. Port **8000**) und die **alte Website** sind zwei URLs. `LEGACY_SITE_URL` muss **genau** die Basis-URL sein, unter der `index.php` und `laravel_handoff.php` erreichbar sind (inkl. Port, z. B. `http://127.0.0.1:8080`, wenn die alte App mit `make php` / `./serve-php.sh` bzw. `php -S … router.php` im Projektroot läuft). Ohne laufenden Webserver auf dieser URL schlägt der Sprung nach dem Login fehl.

**Lokal starten (nach Installation der Frontend-Assets, siehe unten):**

```bash
cd laravel
./serve-test.sh
# oder: composer serve-app
# oder: php artisan serve
```

**Umgebung:** In `laravel/.env` müssen `MONGODB_URI` und `MONGODB_DATABASE` zur Datenbank passen. Nach **dbScripts**-Init heißt die DB in der Regel **`portfolio_db`** (wie `APP_DB_NAME` in `includes/db.php`). Für **Registrierung** und Brücken braucht Laravel **Schreibrecht** auf `users` und `registration_codes` — dafür ist typisch die **Admin-**URI (siehe `laravel/.env.example`); der reine **Viewer-**User reicht dafür oft nicht. Für Tests nutzt `laravel/phpunit.xml` die eigene DB `portfolio_db_test` — bei Bedarf anpassen.

**Frontend bauen (einmalig bzw. nach Änderungen an JS/CSS):**

```bash
cd laravel
npm install
npm run build
```

Ohne `npm run build` schlägt `@vite` in den Blade-Layouts fehl (HTTP 500). Für PHPUnit wird `withoutVite()` im Test-Fallback gesetzt.

**Automatische Tests:**

```bash
cd laravel
php artisan test
```

MongoDB ohne Replica Set unterstützt keine DB-Transaktionen wie Laravels `RefreshDatabase`; im Basis-`TestCase` sind daher **`$connectionsToTransact = []`** gesetzt (Tests teilen sich eine migrierte Test-DB ohne Rollback zwischen den Fällen — bei neuen Tests auf eindeutige Usernames/E-Mails achten).

## Projektstruktur

```text
.
├── index.php                 # Einstiegspunkt und einfacher Router
├── AGENTS.md                 # Arbeitsregeln und Kontext für KI-Agenten
├── docs/                     # Planungen und technische Zusatzdokumentation
├── includes/
│   └── bootstrap.php         # Session, DB-Verbindung, Rechte, Upload-Helfer
├── pages/
│   ├── home.php              # Startseite / Kurzvorstellung
│   ├── account.php           # Login, Registrierung, Invite-Codes
│   ├── impressum.php         # Impressum (Inhalt aus site_pages)
│   ├── project_grid.php      # Projektübersicht
│   ├── project_detail.php    # Detailseite, Likes, Dislikes, Kommentare
│   ├── create_project.php    # Projekt anlegen, Entwürfe, Uploads
│   ├── edit_project.php      # Projekt bearbeiten
│   └── admin/                # Admin-Dashboard, Rollen, Berechtigungen
├── dbScripts/                # Initialisierung und Reset von Collections
├── content/
│   ├── images/               # Hochgeladene Bilder
│   ├── videos/               # Hochgeladene Videos
│   └── tmp/                  # Bearbeitungs-Uploads (nur über media-Proxy, gitignored)
├── router.php                # Dev-Server: Medien-Proxy + /logs blockieren
├── media.php                 # Auth-Auslieferung für content/images|videos|tmp
├── style/                    # CSS-Dateien
├── img/                      # Statische Bilder
└── logs/                     # Lokal: Fehler/Rate-Limits (gitignored; `logs/.htaccess` verweigert Direktzugriff)
```

## Voraussetzungen

- PHP mit MongoDB-Erweiterung
- MongoDB mit konfigurierbaren Verbindungen für App- und Admin-Kontext
- Composer
- Schreibrechte für:
  - `content/images`
  - `content/videos`
  - `logs`

## Entwicklung und Deployment (Arbeitsweise)

Bis auf Weiteres wird **ausschließlich lokal** entwickelt und getestet. Ein Einsatz auf einem entfernten Server (Produktion oder Staging) erfolgt **erst**, wenn dafür ausdrücklich entschieden wurde; vorher fokussieren sich Setup, Konfiguration und Features auf die lokale Umgebung.

## Installation und Start

1. Abhängigkeiten installieren:

```bash
composer install
```

### React/Vite (Styling-Bundling)

Die Website lädt Frontend-Assets aus `react-dist/` über ein Vite-Manifest (`includes/vite_assets.php`).
Der Ordner **`react-dist/` ist Build-Output und wird nicht ins Repository aufgenommen** — nach einem `git clone` (oder wenn der Build fehlt) unbedingt `cd frontend && npm install && npm run build` ausführen.

Damit kann das Styling schrittweise „über React“ kommen, ohne die PHP-Seiten sofort umzubauen.

**Einmalig installieren & bauen:**

```bash
cd frontend
npm install
npm run build
```

**HMR-Workflow (optional, nur für Live-Reload der Frontend-Dateien):**

`includes/vite_assets.php` nutzt **standardmäßig ein gebautes** `react-dist/` (nach `npm run build`). HMR (Skripte von Vite statt Build) muss in `.env.local` (Projektroot) **ausdrücklich** eingeschaltet werden, damit bei gesetzter `VITE_DEV_SERVER_URL` kein reines HMR-Setup nötig ist und Styling zuverlässig über `react-dist/` geht.

```text
VITE_HMR=1
VITE_DEV_SERVER_URL=http://127.0.0.1:5173
```

Terminal 1:

```bash
cd frontend
npm run dev -- --host 127.0.0.1 --port 5173
```

Terminal 2 (PHP im Projektroot, dieselbe Variable muss im PHP-Prozess stehen, z. B. per `.env.local` wie oben):

```bash
php -d upload_max_filesize=2048M -d post_max_size=2100M -S 127.0.0.1:8080 -t . router.php
```

Ohne `VITE_HMR=1` reicht `npm run build` — **kein** laufendes Vite, **kein** `VITE_DEV_SERVER_URL` nötig.

### Lokales Starten (empfohlen / reproduzierbar)

Die Website besteht aus der klassischen PHP-App (Projektroot) + einem Vite/React-Asset-Build unter `react-dist/`.
Für ein **stabil gestyltes** Setup ist es am zuverlässigsten, die Assets einmal zu bauen und dann nur den PHP-Server zu starten.

**Variante A (stabil, empfohlen): PHP + gebautes `react-dist/`**

```bash
cd frontend
npm install
npm run build

cd ..
php -d upload_max_filesize=2048M -d post_max_size=2100M -S 127.0.0.1:8080 -t . router.php
```

Dann öffnen: `http://127.0.0.1:8080/`

**Makefile (Kurzbefehle):** Im Projektroot `make help` — u. a. `make dev` (Build + PHP), `make laravel`, `make mailhog`.

**Docker (MongoDB + MailHog, optional):** `docker compose up -d` oder `make services-up`. MongoDB auf Port 27017, MailHog UI http://127.0.0.1:8025/. PHP/Laravel bleiben auf dem Host. Nach erstem Start DB wie gewohnt per Admin-Skripte initialisieren. Details: `docs/deployment.md`.

**Variante B (HMR, optional):** `npm run dev` in `frontend` starten, in `.env.local` zusätzlich `VITE_HMR=1` **und** `VITE_DEV_SERVER_URL=http://127.0.0.1:5173` (Port wie Vite) setzen, dann `php -S` wie oben. Vite ist mit `base: '/react-dist/'` konfiguriert (`frontend/vite.config.ts`); im HMR-Modus müssen `…/react-dist/@vite/client` u. a. **vom Vite-Port** antworten.

### Wenn kein Styling / keine React-Assets (Fehlersuche)

Die Einbindung steckt in `includes/vite_assets.php` → `vite_react_assets()`.

| Modus | Voraussetzung | Was passiert |
|--------|----------------|--------------|
| **Standard (empfohlen)** | Kein `VITE_HMR=1` (oder weggelassen) | Es werden die **gebauten** Dateien aus `react-dist/.vite/manifest.json` eingebunden. Voraussetzung: `cd frontend && npm run build` war einmal (oder kürzlich) gelaufen. |
| **HMR** | In `.env.local` (o. ä.) u. a. `VITE_HMR=1` **und** `VITE_DEV_SERVER_URL=…` (gleiche Port/Host/Schema wie `npm run dev`) **und** Vite wirklich gestartet, PHP muss Vite per Socket/HTTP erreichen | Skripte (`@vite/client` + Entry) von Vite. **Zusätzlich** werden die im Manifest stehenden **CSS-Dateien** aus `react-dist/` per `<link>` eingebunden, damit Styling erhalten bleibt, falls Module nicht laden. Läuft Vite nicht, fällt die Logik auf vollständiges **Manifest-Rendering** (CSS + JS) zurück, sofern ein Build existiert. |
| **Nur Build erzwingen** | `VITE_USE_BUILT_ASSETS=1` in der Umgebung | Es werden **nur** Manifest-Dateien genutzt (z. B. für Tests/CI), nie Dev-Skripte. |

Typische Fälle ohne Styling:

1. **Kein Build:** Wenn `react-dist/.vite/manifest.json` fehlt, kommen **keine** Link-/Script-Tags (still). *Lösung:* `cd frontend && npm run build`.
2. **Nur alte Doku/Shell:** Früher wurde oft `VITE_DEV_SERVER_URL` **ohne** laufendes Vite benutzt. Jetzt reicht: **HMR** nur mit `VITE_HMR=1` **oder** ganz weglassen und nur per Build arbeiten.
3. **HMR an, Vite aus:** Dann Anzeige meist trotzdem per Fallback aus `react-dist/`, sofern gebaut. Ohne Build bleibt die Seite ungestylt.
4. **HMR, aber fremde/HTTPS-Startseite:** Die Dev-URL stammt aus `VITE_DEV_SERVER_URL` (kein künstliches Umschreiben von Host/Schema); bei **HTTPS-Seite** + **HTTP-Vite** blockieren Browser ggf. Skripte (Mixed Content) — dann HMR ausschalten, nur `npm run build` nutzen, oder Vite/Proxy per HTTPS.

2. Sicherstellen, dass MongoDB lokal läuft:

```text
mongodb://localhost:27017
```

Optional konfigurierbare Umgebungsvariablen:

```text
APP_DB_URI=mongodb://viewer:0@localhost:27017/portfolio_db?authSource=portfolio_db
ADMIN_DB_URI=mongodb://admin:0@localhost:27017/portfolio_db?authSource=portfolio_db
APP_DB_NAME=portfolio_db
```

Für die geplante rollenbasierte MongoDB-Absicherung werden später diese Variablen verwendet:

```text
VIEWER_DB_URI=mongodb://viewer:0@localhost:27017/portfolio_db?authSource=portfolio_db
COMMUNITY_DB_URI=mongodb://community_member:0@localhost:27017/portfolio_db?authSource=portfolio_db
CONTENT_MANAGER_DB_URI=mongodb://content_manager:0@localhost:27017/portfolio_db?authSource=portfolio_db
ADMIN_DB_URI=mongodb://admin:0@localhost:27017/portfolio_db?authSource=portfolio_db
APP_DB_NAME=portfolio_db
```

Empfohlene Zielarchitektur:

- `VIEWER_DB_URI` wird für Gäste und `viewer` verwendet.
- `COMMUNITY_DB_URI` wird für `community_member` verwendet.
- `CONTENT_MANAGER_DB_URI` wird für `content_manager` verwendet.
- `ADMIN_DB_URI` wird für `admin` sowie DB-Initialisierung und Wartung verwendet.
- Wenn keine Env-Variablen gesetzt sind, bricht die App ab (keine stillen Default-Passwörter mehr). Für lokale Entwicklung: `.env.local` aus `.env.example` anlegen **oder** `APP_ALLOW_DEV_DB_DEFAULTS=1` setzen (dann weiterhin `*:0` wie früher).
- `?debug=1` zeigt Fehler nur bei `APP_ENV=local` und Zugriff von `127.0.0.1` / `::1`.
- Alle mutierenden POST-Requests der klassischen Website benötigen ein CSRF-Token (`includes/csrf.php`, `js/csrf-forms.js`). Logout nur per POST.
- Optional: `SESSION_SECURE=1`, `TRUSTED_PROXY_IPS` (kommagetrennt) für Betrieb hinter HTTPS/Reverse-Proxy — siehe `.env.example` im Projektroot.
- Mongo-RBAC: App-Rollen (`viewer` usw.) lesen **keine** `users`- oder `registration_codes`-Collections mehr; Einladungen/Admin über `admin`. Nach Änderung an `03_db_init_mongo_roles.php` Skript im Admin ausführen. Kommentar-Anzeigenamen: `includes/user_db.php`.
- Autorisierung: `includes/authz.php` erzwingt Rechte serverseitig. E-Mail-Nachweis nur im Registrierungs-Anfrage-Flow (`verify_registration_request`), nicht als Laravel-Login-Gate. Plan: `docs/next_session_plan.md`.

### Erste Initialisierung, wenn MongoDB noch keine Projektbenutzer hat

Die Standard-URIs (ohne gesetzte Umgebungsvariablen) verbinden sich als `viewer:0`, `admin:0` usw. Diese MongoDB-Benutzer werden erst durch `dbScripts/03_db_init_mongo_roles.php` angelegt. **Vor dem ersten erfolgreichen Lauf der Initialisierung** führt das zu `Authentication failed`, wenn die Umgebungsvariablen nicht angepasst werden.

**Vorgehen:** Vor dem ersten Ausführen von `dbScripts/db_init_master.php` (CLI oder PHP Built-in Server) alle relevanten URIs auf eine Verbindung **ohne** Datenbankbenutzer setzen (typisch: gleiche URI für alle Rollen):

```bash
export APP_DB_URI='mongodb://localhost:27017/portfolio_db'
export VIEWER_DB_URI='mongodb://localhost:27017/portfolio_db'
export COMMUNITY_DB_URI='mongodb://localhost:27017/portfolio_db'
export CONTENT_MANAGER_DB_URI='mongodb://localhost:27017/portfolio_db'
export ADMIN_DB_URI='mongodb://localhost:27017/portfolio_db'
export APP_DB_NAME='portfolio_db'
```

Erst danach können die Skripte die Collections anlegen und in Schritt `03_*` die MongoDB-Benutzer mit den gewählten Passwörtern erstellen. **Anschließend** kannst du die Shell-Variablen entfernen und die Standard-URIs aus `includes/db.php` nutzen (Passwort `0`), oder die Variablen auf die authentifizierten URIs aus dem Abschnitt „Optional konfigurierbare Umgebungsvariablen“ setzen.

**Erster Lauf ohne Admin-Account:** Das Admin-Dashboard (`pages/admin/index.php`) ist nur für eingeloggte Admins erreichbar. Für die allererste Initialisierung eignet sich die Ausführung per PHP-CLI: `ALLOW_DB_SCRIPT_EXECUTION` auf `true` setzen, `$GLOBALS['dbScriptInput']` mit Seed-Admin und Mongo-Passwörtern füllen und `dbScripts/db_init_master.php` einbinden (analog zum Admin-Dialog).

Direkter Browserzugriff auf Dateien unter `dbScripts/` ist durch `dbScripts/_guard.php` blockiert; die Initialisierung aus dem Admin-Panel setzt dieselbe Freigabe intern.

## MongoDB Authentifizierung

Damit die Trennung der Datenbankrechte tatsächlich wirksam ist, muss MongoDB-Authentifizierung aktiviert sein. Nur das Anlegen von Benutzern in MongoDB reicht nicht aus, wenn der Server weiterhin ohne Auth läuft.

Erforderlich:

- MongoDB-Admin-Benutzer anlegen
- in der MongoDB-Konfiguration Authentifizierung aktivieren
- MongoDB-Dienst neu starten
- danach die Projektbenutzer `viewer`, `community_member`, `content_manager` und `admin` anlegen

Typische MongoDB-Konfiguration:

```yaml
security:
  authorization: enabled
```

Danach müssen sich Compass, `mongosh` und die Website mit gültigen Zugangsdaten verbinden.
Die Anwendung kann abhängig von der Website-Rolle unterschiedliche MongoDB-Verbindungen verwenden.

## Rollenbasierte MongoDB-Benutzer

Für die geplante granulare Datenbankabsicherung werden getrennte MongoDB-Benutzer pro Website-Rolle empfohlen:

- `viewer`
- `community_member`
- `content_manager`
- `admin`

Die Verbindungs-URIs dafür stehen im Abschnitt zu den Umgebungsvariablen.
Die rollenbasierte Auswahl ist im Projekt bereits vorbereitet und wird zentral in `includes/db.php` aufgelöst.

## Wichtiger Architekturhinweis

MongoDB-Rollen sind grob und schützen primär auf Datenbank- oder Collection-Ebene.

Das ist sinnvoll für technische Grenzen wie:

- wer grundsätzlich schreiben darf
- wer administrative DB-Aktionen ausführen darf
- wer nur lesen darf

Feine fachliche Kontrolle bleibt trotzdem Aufgabe der Anwendung, zum Beispiel:

- wer Kommentare schreiben darf
- wer Projekte erstellen darf
- wer nur eigene Projekte ändern darf
- wer Uploads ausführen darf

Diese Regeln müssen zusätzlich serverseitig im Code erzwungen werden und dürfen nicht nur in der UI verborgen sein.

3. Datenbank initialisieren:

- **Erster Lauf (noch kein Admin):** per PHP-CLI mit gesetzten Umgebungsvariablen ohne Mongo-Benutzer (siehe Abschnitt „Erste Initialisierung …“ oben).
- **Später:** aus dem Admin-Bereich über `pages/admin/index.php` (eingeloggter Admin).

Die Initialisierung erstellt Collections, Rollen, Berechtigungen, Invite-Codestruktur und einen Admin-User.

## Standardzugang nach Initialisierung

Das Script `dbScripts/00_db_init_accounts.php` legt standardmäßig folgenden Admin an:

- E-Mail, Username und Passwort werden beim Ausführen des Scripts im Admin-Panel abgefragt.
- Beim Ausführen von `dbScripts/db_init_master.php` werden diese Eingaben ebenfalls im Dialog abgefragt.

Wichtig: Harte Seed-Credentials sollen nicht mehr im Code hinterlegt werden.

## Routing

Die Anwendung nutzt Query-Parameter-basiertes Routing:

- `index.php?page=project_grid`
- `index.php?page=project_detail&id=<project_id>`
- `index.php?page=create_project`
- `index.php?page=edit_project&id=<project_id>`
- `index.php?page=account`

Für unbekannte Seiten wird `pages/404.php` geladen.

Zusätzlich unterstützt `index.php` einfache AJAX-Requests, indem Zielseiten direkt aus `pages/<name>.php` geladen werden.

## Rollen und Berechtigungen

Die Rollen werden in `roles_config`, die Berechtigungen in `permissions_config` gespeichert.

Standardrollen:

- `viewer`
  - Darf Projekte, Kommentare und Likes ansehen
- `community_member`
  - Zusätzlich Kommentare schreiben und Likes/Dislikes setzen
  - Hat standardmäßig ein Kommentar-Limit von 10
- `content_manager`
  - Darf Projekte erstellen und **alle** Projekte bearbeiten (`edit_all`, wie Admin für Bearbeitung – ohne `delete_all` / Nutzerverwaltung)
  - Darf kommentieren, liken/disliken und bestimmte Kommentare löschen
  - In der Projekt-Detailansicht wird bei Kommentaren die **Rolle** des Autors angezeigt (Label aus `roles_config`)
- `admin`
  - Vollzugriff auf Inhalte, Löschfunktionen, Invite-Codes, Rollen/Berechtigungen und Initialisierungsskripte

Wichtige Standard-Berechtigungen:

- `view_projects`
- `view_comments`
- `view_likes`
- `create_project`
- `edit_all`
- `edit_own`
- `delete_all`
- `delete_comments`
- `comment_limit`
- `manage_users`
- `generate_codes`
- `comment`
- `like_dislike`

Die Funktion `can($permission)` in `includes/bootstrap.php` dient als zentrale Rechteprüfung.

## Account- und Invite-System

Die klassischen Auth-Seiten sind aufgeteilt:

- **Login**: `index.php?page=login` (Formular → `bridge_auth.php`)
- **Registrierung**: `index.php?page=register` (Formular → `bridge_register.php`, Invite-/Registrierungscode via `reg_token`)
- **Account-Dashboard (eingeloggt)**: `index.php?page=account` (Logout + Admin: Einladungscodes/Links)

Einladungscodes werden weiterhin in `registration_codes` gespeichert (Einmalverwendung).

### Registrierungscode anfragen (E-Mail-Verifikation + Admin-Freigabe)

Wenn noch kein Registrierungscode vorhanden ist, kann auf `index.php?page=register` eine Anfrage mit **E-Mail-Adresse** gestellt werden.
Der Ablauf ist:

- Anfrage absenden → es wird ein Bestätigungslink per Mail gesendet (`page=verify_registration_request&token=...`, gültig für 24h)
- nach Klick auf den Link steht die Anfrage im Admin-Bereich als „verifiziert, warte auf Freigabe“
- Admin gibt frei → es wird ein Registrierungscode erzeugt und per Mail versendet

Technik:

- Collection: `registration_code_requests` (Token + Metadaten, Verifikation/Freigabe)
- Admin-Seite: `pages/admin/registration_requests.php`
- Mailversand: Standard PHP `mail()`; optional SMTP über `includes/mail.php`:
  - `MAIL_SMTP_HOST`, `MAIL_SMTP_PORT` (z. B. MailHog `127.0.0.1:1025` ohne Verschlüsselung)
  - `MAIL_SMTP_ENCRYPTION`: `tls` (STARTTLS, typ. Port 587), `ssl` (465) oder `none` (nur lokal)
  - `MAIL_SMTP_USERNAME` / `MAIL_SMTP_PASSWORD` (Provider-Auth)
  - `MAIL_FROM_EMAIL`, `MAIL_LOG_REDACT_SECRETS=1` (Default: Token/Codes in `logs/mail.log` redigiert)
- **Produktion:** Bei `APP_ENV=production` ist Plain-SMTP (`encryption=none`) blockiert; Vorlage [`.env.production.example`](.env.production.example), Prüfung `make prod-env-check` / Admin **Deploy-Status**.
- Lokal ohne SMTP: `mail.log` enthält Test-URLs. MailHog: `make services-up`, `make mailhog-check`, Setup **`docs/local_mail_setup.md`**.

Die Auth-/Invite-Funktionen umfassen:

- Login per E-Mail oder Username
- Registrierung per Registrierungscode
- Rollenzuweisung anhand des verwendeten Registrierungscodes
- Generierung neuer Einladungscodes für berechtigte Nutzer
- Robustheit/Skalierung: Codes sind lang genug für praktisch kollisionsfreie Generierung und werden bei Duplicate-Key automatisch neu generiert (Unique-Index + Retry).
- **Einladungs-Direktlinks** in der Tabelle: `index.php?page=register&reg_token=<CODE>#register-section` (führt zur Registrierungsseite und füllt den Code in das Feld **Registrierungscode** vor; der Link muss in HTML-Attribute als Text ausgegeben werden, damit `&reg_token` nicht als HTML-Entity `&reg;` in `®_token` verfälscht wird). **Legacy:** alte Links auf `page=account&reg_token=...` werden serverseitig auf `page=register` umgeleitet.
- In der Tabelle stehen neben **Code** und **Direkt-Link** **Kopier-Buttons** (Clipboard), damit man Werte schnell teilen kann
- Meldungen/Alerts sind themefähig (Light/Dark über CSS-Variablen), damit sie im **Darkmode** lesbar bleiben

Invite-Codes werden in `registration_codes` gespeichert. Ein Code kann nur einmal verwendet werden.

## Projekte und Medien

Projekte werden in der Collection `projects` gespeichert. Ein Projekt kann enthalten:

- Titel
- Beschreibung
- Tags
- Galerie mit Bild- und/oder Videodateien
- optionales Thumbnail
- `author_id`
- `is_draft`
- Zeitstempel für Erstellung und Aktualisierung

Uploads werden lokal gespeichert:

- Bilder unter `content/images`
- Videos unter `content/videos`

Upload-Regeln (Standardwerte, im Admin unter **Einstellungen** anpassbar; technisch `includes/site_settings.php` → Konstanten in `includes/bootstrap.php`):

- maximal 50 Dateien pro Upload-Vorgang
- Bilder bis **50 MB**, Auflösung max. **4K** (3840×2160 px; längere Seite ≤ 3840, kürzere ≤ 2160)
- Videos bis **2 GB** (für ca. 10 min 1080p bei üblichen Codecs/Bitraten)

**PHP-Server-Limits:** Die Anwendung lehnt größere Dateien ab, aber PHP muss sie zuerst annehmen. Standard-`php.ini` erlaubt oft nur wenige MB (`upload_max_filesize` / `post_max_size`) — dann erscheint im Netzwerk-Tab zwar **POST**, aber PHP verwirft den Body still. Lokal **`make php`** oder **`./serve-php.sh`** (beides **2048M / 2100M**). Bei barem `php -S` dieselben `-d`-Werte setzen oder Apache/nginx mit `.user.ini` im Projektroot. Es gibt **kein** separates Gesamt-Limit pro Projekt (nur pro Datei und max. 50 Dateien pro Upload-Vorgang). Nach Änderung den Webserver neu starten.

`pages/create_project.php` unterstützt Entwürfe. Bereits hochgeladene Medien können also zwischengespeichert, sortiert und einzeln gelöscht werden, bevor das Projekt final gespeichert wird.

## Interaktionen

`pages/project_detail.php` implementiert:

- Likes/Dislikes pro Medium innerhalb eines Projekts
- Kommentare pro Medium
- Antworten auf Kommentare
- Löschlogik für Kommentare abhängig von Rolle und Besitz
- AJAX-Antworten für Interaktionen

Datenhaltung:

- `likes`: speichert Reaktion eines Nutzers pro `project_id` + `media_id`
- `comments`: speichert Kommentare inklusive optionaler `parent_comment_id` für Antworten

## Admin-Bereich

Der Admin-Bereich liegt unter `pages/admin/` und umfasst:

- `index.php`
  - Dashboard (Übersicht + effektive DB-Konfiguration)
- `home_profile.php`
  - Startseiten‑Profil pflegen (Name, Position, Kurztext, Langtext, Portrait)
- `legal_page_edit.php?key=…`
  - Impressum, Datenschutz oder Nutzungsbedingungen einzeln bearbeiten (eigene Admin-Menüpunkte; Datensätze in `site_pages`)
- `settings.php`
  - Allgemeine Einstellungen (Website-Name, Medien-Limits, Galerie-Paging, Kommentarlänge; Datensatz `site_settings` in `site_pages`, nur Admin). Der Name gilt auch für Laravel-Auth-Seiten (z. B. Passwort vergessen) und System-E-Mails (`laravel/app/Services/SiteDisplayName.php`).
- `db_scripts.php`
  - Ausführung und Einsicht der Datenbankskripte aus `dbScripts/`
  - Vor Ausführung: CSRF + **frische Admin-Bestätigung** (Passwort, bei aktivem 2FA zusätzlich TOTP; danach 15 Min. gültig — `includes/admin_reauth.php`)
  - Nach erfolgreicher Ausführung trackbarer Skripte (ab `03_…`, nicht `00`–`02`): Eintrag in `schema_migrations` — auch wenn sie über `db_init_master.php` liefen (`includes/schema_migrations.php`)
- `deploy_status.php`
  - Read-only-Checkliste (Frontend-Build, effektive Mongo-URIs, Laravel-Env, **DB-Skript-Protokoll** mit „X von Y protokolliert“, Schreibrechte) — **kein** Code-Deploy aus dem Browser
- `roles.php`
  - Rollen anlegen, bearbeiten und löschen
- `permissions.php`
  - Berechtigungen anlegen, bearbeiten und löschen

Zugriff ist für eingeloggte Nutzer mit Rolle `admin` vorgesehen; `home_profile.php` und die Rechtstexte sind zusätzlich für `content_manager` freigeschaltet.

### Admin: Master-Layout + Navigation

Die Admin-Seiten nutzen ein gemeinsames „Rahmen“-Layout in `pages/admin/_layout.php` (Theme-Bootstrap `data-theme`, Vite-Assets, Navigation). Inhaltseiten rendern ihren Body über `admin_render_page(...)`, damit Navigation/Grundstruktur nicht pro Datei dupliziert werden muss.

- Menüpunkte: Dashboard, **Nutzer** (Suche, Timeout/Ban), **DB-Skripte**, **Deploy-Status**, Registrierungsanfragen, Rollen, Berechtigungen, Zur Hauptseite
- **Nutzer-Moderation:** `pages/admin/users.php` setzt `account_moderation` auf `users` (Timeout mit Ablauf, permanenter Ban, optionaler Grund für den Nutzer). Gesperrte Konten werden beim Login (`bridge_auth.php`), bei 2FA-Handoff (`bridge_auth_2fa.php`), Laravel-Login und Session-Sync (`includes/authz.php`) abgewiesen. Admin-Konten sind geschützt. Index: `dbScripts/13_db_init_users_moderation.php`.
- Der aktive Menüpunkt wird hervorgehoben (CSS: `.admin-nav a.is-active`)
- `index.php` zeigt **nur im Dashboard** die effektive DB-Konfiguration (laufender PHP‑Prozess) plus ein paar simple Metriken (z. B. Anzahl `users`/`projects`)

## Datenbankskripte

Die wichtigsten Initialisierungsskripte:

- `dbScripts/00_db_init_accounts.php`
  - Accounts, Rollen, Berechtigungen, Registrierungscodes, Admin-Benutzer
- `dbScripts/01_db_init_content.php`
  - Projects-Collection und Beispielprojekt
- `dbScripts/02_db_init_interactions.php`
  - Comments- und Likes-Collections
- `dbScripts/03_db_init_mongo_roles.php`
  - MongoDB-Custom-Roles und MongoDB-Benutzer für `viewer`, `community_member`, `content_manager` und `admin`
- `dbScripts/04_db_users_validator_allow_laravel.php`
  - **Nicht destruktiv:** passt nur den MongoDB-Validator der Collection `users` an (`additionalProperties: true`), damit Laravel zusätzliche Felder (`remember_token` usw.) speichern kann. Einmal ausführen, wenn die Registrierung mit „Document failed validation“ fehlschlägt (bestehende DB nach älterem `00_db_init_accounts`).
- `dbScripts/05_db_init_site_pages.php`
  - `site_pages`-Collection + Startseite `home_profile` aus `site_page_home_profile_defaults()` (destruktiv: droppt die Collection)
- `dbScripts/06_db_init_site_impressum.php`
  - Impressum in `site_pages` aus `site_page_legal_defaults()` (Platzhalter; ersetzt nur diesen Datensatz)
- `dbScripts/07_db_init_site_datenschutz.php`
  - Datenschutz in `site_pages` aus `site_page_legal_defaults()` inkl. optionalem TOTP-2FA (ersetzt nur diesen Datensatz)
- `dbScripts/08_db_init_site_nutzungsbedingungen.php`
  - Nutzungsbedingungen in `site_pages` aus `site_page_legal_defaults()` (ersetzt nur diesen Datensatz)
- `dbScripts/09_db_init_site_settings.php`
  - Allgemeine Einstellungen (`site_settings` in `site_pages`, inkl. `site_name`). Standard: Datensatz anlegen bzw. fehlende Felder ergänzen (bestehende Upload-Limits bleiben). Optional per Checkbox im Admin: alle Werte auf Standard zurücksetzen.
- `dbScripts/11_db_init_users_two_factor.php`
  - Dokumentation der optionalen `two_factor_*`-Felder auf `users` + sparse Index
- `dbScripts/13_db_init_users_moderation.php`
  - Dokumentation von `account_moderation` (Timeout/Ban) auf `users` + sparse Index
- `dbScripts/14_db_init_handoff_tokens.php` (TTL/Unique für Handoff-Nonces)
- `dbScripts/15_db_init_security_baseline.php` (idempotent: Handoff- + Projekt- + Moderation-Indizes; beliebig wiederholbar)
- `dbScripts/16_db_init_schema_migrations.php` (idempotent: Collection `schema_migrations` + Unique-Index; im Admin optional Checkbox „Protokoll auffüllen“ für trackbare Skripte nach älterem Master-Lauf)
- `dbScripts/db_init_master.php`
  - Führt die nummerierten Skripte gesammelt aus

Hinweis: Die Skripte droppen Collections und setzen Daten neu auf. Sie sind daher destruktiv.
Direkter Browserzugriff auf `dbScripts/` ist gesperrt. Die Ausführung soll nur über den Admin-Bereich erfolgen.
Normale Seiten verwenden die App-Datenbankverbindung, DB-Initialisierung und Wartung verwenden eine getrennte Admin-Verbindung.
Alle nummerierten Dateien in `dbScripts/` werden von `dbScripts/db_init_master.php` automatisch mit ausgeführt.

Für `dbScripts/03_db_init_mongo_roles.php` werden diese Env-Variablen für die MongoDB-Passwörter erwartet:

```text
MONGO_VIEWER_DB_PASSWORD=...
MONGO_COMMUNITY_DB_PASSWORD=...
MONGO_CONTENT_MANAGER_DB_PASSWORD=...
MONGO_ADMIN_DB_PASSWORD=...
```

Wenn diese fehlen, kann das Script die MongoDB-Benutzer nicht vollständig initialisieren.
Im Admin-Panel können diese Passwörter alternativ direkt beim Ausführen eingegeben werden. Die Eingabefelder sind verpflichtend.
Beim Ausführen von `dbScripts/db_init_master.php` werden die relevanten Felder ebenfalls gesammelt im Master-Dialog abgefragt.

## Debugging

Mit `?debug=1` werden erweiterte PHP-Fehler aktiviert. `includes/bootstrap.php` schreibt Fehler dann zusätzlich nach:

- `logs/php_errors.log`

Beispiel:

```text
index.php?page=project_grid&debug=1
```

## Bekannte Eigenschaften des aktuellen Stands

- Die Anwendung ist stark auf lokale Entwicklung mit einer lokalen MongoDB-Instanz ausgelegt.
- Konfiguration wie Datenbank-URI oder Admin-Seed ist derzeit im Code fest hinterlegt.
- Produktions-Vorlage: [`.env.production.example`](.env.production.example) (auf dem Server als `.env.local`); Deploy-Hilfen: `make deploy-check`, `make deploy-server`, [docs/deployment.md](docs/deployment.md).
- Die Anwendung nutzt kein Framework und keine API-Schicht; Rendering und Logik liegen direkt in den PHP-Seiten.

## Empfohlene nächste Dokumente

Falls das Projekt weiter wächst, wären diese Ergänzungen sinnvoll:

- `docs/architecture.md` für Seitenfluss und Rechtekonzept
- `docs/database.md` für Collections und Felder
- `docs/deployment.md` für lokale Docker-Dienste und Entwurf Server/DynDNS
- `docs/security.md` für Invite-System, Medien-Proxy, Uploads und Härtung
- `docs/security_local_checklist.md` für iterative lokale Security-Tests (abhackbar)
- `docs/current_status.md` für aktuelle Blocker und den letzten technischen Zwischenstand
