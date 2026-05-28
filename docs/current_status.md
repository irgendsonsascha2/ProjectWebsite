# Current Status

**Hinweis (Arbeitsweise):** Es wird vorerst **nur lokal** weiterentwickelt; ein Deployment auf einen Server steht an, sobald dafür ausdrücklich entschieden wurde (siehe auch `README.md` → *Entwicklung und Deployment (Arbeitsweise)*).

## Stand vom 2026-05-28 (Details: Abschnitte unten; Session-Plan: `docs/next_session_plan.md`)

Aktueller Blocker bei der Sicherheits- und MongoDB-Umstellung:

- Die Website ist wieder grundsätzlich startbar.
- Der lokale PHP-Server lässt sich über `php -S localhost:3000` starten.
- MongoDB-Authentifizierung ist aktiv.
- Die MongoDB-Benutzer `viewer`, `community_member`, `content_manager` und `admin` existieren in `portfolio_db`.
- Der MongoDB-Benutzer `admin` hat laut Prüfung diese Rollen:
  - `dbAdmin`
  - `dbOwner`
  - `userAdmin`

## Konkretes Problem (historisch / andere Umgebungen)

Früher blockierte `03_db_init_mongo_roles.php` mit `not authorized` oder `Authentication failed` (falsche URIs, Prozess ohne `.env.local`, Windows-Env).

**Lokal (2026-05-21):** Admin-Ping und Custom-Rollen OK; Indizes `11`/`13` per CLI erfolgreich. Nach Passwort-Änderung weiterhin `03` im Admin ausführen und PHP-Server neu starten, damit der Prozess die URIs aus `.env.local` lädt.

## Sicherheit Sprints 1–5 (2026-05, zusammengefasst)

Umgesetzt (Details in [security.md](security.md), Tests [security_local_checklist.md](security_local_checklist.md)):

- CSRF, Session-Cookies, Handoff `session_regenerate`, POST-Logout, eingeschränktes `?debug=1`, Mongo-URIs ohne `.env.local` nur mit `APP_ALLOW_DEV_DB_DEFAULTS`.
- Mongo RBAC, `user_db.php`, Projekt-Indizes; Handoff nur über `ADMIN_DB_URI`.
- `authz.php`, Rate-Limits, kein Laravel-E-Mail-Gate; Admin-CSRF; optionales TOTP-2FA; Nutzer-Moderation.

**Nach Pull:** `03_db_init_mongo_roles.php` im Admin (oder Master), optional `10_db_init_projects_indexes.php`.

### Input- und NoSQL-Härtung (2026-05-28)

- Neue Schicht [`includes/mongo_input_guard.php`](../includes/mongo_input_guard.php): sichere POST-Listen, Operator-Keys (`$`, `.`), `req_get_token_hex`, `req_post_objectid_list`, Rollen-Permissions aus Checkbox-**Werten** mit DB-Whitelist.
- Passwörter (Register/Reset): Laravel `min(12)` / `max(512)`, Unicode ohne Symbol-Zwang; klassisches Register mit `password_confirmation`. Seed/Mongo-Passwörter: `input_secret_password` in `dbScripts` (u. a. `00_db_init_accounts.php` via `db_script_resolve_password`).
- Doku: [input_validation.md](input_validation.md), [security_input.md](security_input.md), `README.md` (Passwort-Policy).

Offen: Produktions-Server/DynDNS ([deployment.md](deployment.md)); optional Admin-`onclick` → `data-confirm` (CSP). Lokal: `make services-up`.

## Session-Handoff

**Start:** [next_session_plan.md](next_session_plan.md) (offen/erledigt, Smoke: [smoke_test.md](smoke_test.md)).

## Bereits umgesetzte Änderungen (Auswahl)

- Direkter Browserzugriff auf `dbScripts/` ist gesperrt.
- `dbScripts/`-Skripte haben einen Laufzeit-Guard.
- `db_init_master.php` führt nummerierte Skripte automatisch aus.
- Das Admin-Panel fragt sensible Eingaben für:
  - `00_db_init_accounts.php`
  - `03_db_init_mongo_roles.php`
  - `db_init_master.php`
  per Dialog ab.
- Die App unterstützt rollenbasierte MongoDB-Verbindungen:
  - `VIEWER_DB_URI`
  - `COMMUNITY_DB_URI`
  - `CONTENT_MANAGER_DB_URI`
  - `ADMIN_DB_URI`
- DB-Skript-Ausführung wurde stabilisiert:
  - Admin-Dashboard zeigt die **effektive** DB-Konfiguration des laufenden PHP-Prozesses (Passwörter maskiert), um Env-Probleme sofort sichtbar zu machen.
  - `03_db_init_mongo_roles.php` läuft im Admin-Panel garantiert über `ADMIN_DB_URI` (kein versehentlicher Rollen-Fallback).
  - Optionaler `.env.local`-Workflow: `/.env.local` wird beim Start automatisch geladen; beim Ausführen von `03_db_init_mongo_roles.php` kann das Admin-Panel (Checkbox) die URIs in `.env.local` mit den neuen Passwörtern aktualisieren.

## Vite-PHP-Brücke (`includes/vite_assets.php`) – Stand 2026-04-26

- HMR (`.env` mit `VITE_HMR=1` und `VITE_DEV_SERVER_URL`) nur, wenn Vite wirklich läuft; sonst Rückfall auf `react-dist/`-Manifest.
- Im HMR-Modus werden **zusätzlich** die im Manifest verlinkten **CSS-Dateien** aus dem letzten `npm run build` per `<link>` geladen, damit die Seite nicht ohne Styling bleibt, falls ES-Module von Vite nicht geladen werden (Netzwerk, Host/Port, Mixed Content).
- `VITE_USE_BUILT_ASSETS=1` erzwingt ausschließlich Manifest, ohne Dev-Skripte (z. B. CI).
- Doku: `README.md` (React/Vite, Tabelle *Wenn kein Styling*), `AGENTS.md` (Lokaler Start).

## Frontend/React (Hybrid) – UI (2026-05-21)

- **Glass:** zentrale Utilities in `frontend/src/styles/components/glass.css` (Grid/Detail/Create-Edit).
- **CSS-Split:** `frontend/src/app.css` importiert `styles/*` (tokens, layout, pages, admin, components).
- **Responsive:** Admin-Mobile-Pass; Account-Tabellen/Code-Form; Lightbox/FABs auf schmalen Viewports.
- **Copy-UI Account:** Abstände in `frontend/src/styles/pages/account.css` (`[data-react-copy-field]`).
- **Seiten-JS:** Inline-Skripte nach `js/` (z. B. `project-detail.js`, `project-media-manager.js`); Laden über `index.php` je `page=`.
- **CSP:** `script-src 'self'` (Legacy + Laravel); CSRF nur noch per `<meta name="csrf-token">` + `js/csrf-forms.js`. `script-src-attr 'unsafe-inline'` bleibt für vereinzelte `onclick` im Admin.

## Geplant: Mini-CMS (Option A – in der eigenen App)

Ziel: Inhalte ohne Code-Änderungen pflegen (Portfolio/Posts/Seiteninhalte) – passend zum bestehenden Rollen-/Permissions-Modell.

Minimaler v1-Umfang (geplant):

- **Seiteninhalte als Datenmodell**: neue Collection z. B. `pages_content` (Home/About/Impressum) + Rendering in PHP.
- **Admin-UI für Content**: Bearbeiten + Preview + Speichern (mind. Text/Markdown; Richtext optional).
- **Draft/Publish konsistent**: Status + `published_at` für Seiten und (falls noch uneinheitlich) Projekte.
- **Medienbibliothek**: Metadaten (alt, type, owner, timestamps), Wiederverwendung, Aufräumen/Löschen, Referenzen zu Projekten/Seiten.
- **Permissions**: `content_manager` darf Content/Projekte/Medien, aber keine DB-Skripte/Rollenverwaltung; `admin` darf alles.

## Backlog (kurz, ehemals `PLAN.md` im Root)

- Admin-Zugangsprozess: klarer Workflow/Anfrage an Admin für Zugangsdaten oder Freigabe.
- Registrierung / E-Mail: SMTP und Templates produktionsreif (siehe auch `includes/mail.php` / Konfiguration).
- Lokal-Setup (2026-04-26): MailHog-SMTP (Plain) fürs Testen + Laravel läuft lokal für „Passwort vergessen“; `users.email`/`users.username` sind per Unique-Index vorgesehen (DB-Skript `04_db_users_validator_allow_laravel.php` zieht das auf bestehenden DBs nach).
- Responsive Pass, 2FA, Sicherheits-Review und Deployment: parallel zu `security_roadmap.md` und den offenen UI-Punkten oben.

## Nächster sinnvoller Einstieg

Beim nächsten Arbeitsstand zuerst prüfen:

1. Mit welchen effektiven Verbindungsdaten der PHP-Serverprozess wirklich läuft.
2. Ob `ADMIN_DB_URI` im laufenden Prozess tatsächlich `admin@portfolio_db` mit dem richtigen Passwort verwendet.
3. Ob `03_db_init_mongo_roles.php` nach einem sauberen Neustart des Servers mit explizit gesetzten URIs weiterhin `Authentication failed` oder `not authorized` liefert.

## Aufräumen + Security-Pass (2026-05-21)

- Toter Code entfernt; `includes/registration_codes.php`; SVG-Upload gesperrt.
- Handoff mit Einmal-Nonce (`handoff_tokens`, `dbScripts/14_db_init_handoff_tokens.php`).
- Security-Header (PHP + Laravel); Rate-Limits für Kommentare und Handoff-Fehler.
- Laravel verschlankt (kein Verify-Email/Dashboard/Profil im Hybrid).
- Docker: Mongo/MailHog nur `127.0.0.1`.
- Admin-Dialoge: `js/admin-dialogs.js` am Seitenende; `js/theme-bootstrap.js` (Site + Admin).

### Dependency-Audit (2026-05-21)

| Bereich | Ergebnis |
|---------|----------|
| Root `composer audit` | Keine Advisories |
| `laravel/composer audit` | ✅ Symfony-Pakete aktualisiert (2026-05-21), keine offenen Advisories |
| `frontend/npm audit` | (bei Bedarf `npm audit` lokal; Build OK) |

## Betroffene Dateien

- `includes/db.php`
- `includes/bootstrap.php`
- `pages/admin/index.php`
- `dbScripts/00_db_init_accounts.php`
- `dbScripts/03_db_init_mongo_roles.php`
- `dbScripts/db_init_master.php`
