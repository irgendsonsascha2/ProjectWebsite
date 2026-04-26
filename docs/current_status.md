# Current Status

**Hinweis (Arbeitsweise):** Es wird vorerst **nur lokal** weiterentwickelt; ein Deployment auf einen Server steht an, sobald dafür ausdrücklich entschieden wurde (siehe auch `README.md` → *Entwicklung und Deployment (Arbeitsweise)*).

## Stand vom 2026-04-01 (Aktualisierungen: siehe unten, z. B. Vite-Loader 2026-04-26)

Aktueller Blocker bei der Sicherheits- und MongoDB-Umstellung:

- Die Website ist wieder grundsätzlich startbar.
- Der lokale PHP-Server lässt sich über `php -S localhost:3000` starten.
- MongoDB-Authentifizierung ist aktiv.
- Die MongoDB-Benutzer `viewer`, `community_member`, `content_manager` und `admin` existieren in `portfolio_db`.
- Der MongoDB-Benutzer `admin` hat laut Prüfung diese Rollen:
  - `dbAdmin`
  - `dbOwner`
  - `userAdmin`

## Konkretes Problem

Das Admin-Panel kann `dbScripts/03_db_init_mongo_roles.php` weiterhin nicht erfolgreich ausführen.

Zuletzt beobachtete Fehler:

- zunächst `not authorized on portfolio_db to execute command`
- danach `Authentication failed`

Der genaue Endzustand ist damit:

- Es ist noch nicht verifiziert, dass der laufende PHP-Server tatsächlich mit den beabsichtigten MongoDB-URIs und Passwörtern gestartet wurde.
- Es ist noch nicht abschließend geklärt, ob der Prozess wirklich die gesetzten PowerShell-Umgebungsvariablen übernimmt.
- `03_db_init_mongo_roles.php` konnte deshalb noch nicht erfolgreich als vollständiger Sync-Lauf bestätigt werden.

## Bereits umgesetzte Änderungen

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

## Frontend/React (Hybrid) – offene UI-Punkte

- Glass / halbtransparente Overlays standardisieren:
  - Aktuell gibt es mehrere halbtransparente UI-Flächen (z. B. Grid-Caption, Metrics-Pills, Comment-Preview, Lightbox-Overlay) mit leicht unterschiedlichen Farben/Blur-Werten.
  - Fix geplant: zentrale Tokens/Variablen für „Glass“ definieren (Background-Farbe/Alpha, Border, Blur/Saturate) und alle entsprechenden Komponenten darauf umstellen, damit Light/Dark konsistent wirkt.

- Einladungscodes – Kollisions-/Skalierungsproblem:
  - Aktuell werden Codes als 8 Hex-Zeichen erzeugt: `strtoupper(bin2hex(random_bytes(4)))` → nur \(2^{32}\) Möglichkeiten.
  - Bei vielen generierten Codes werden Duplikate/Kollisionen wahrscheinlich (Birthday-Problem) und aktuell gibt es keinen Unique-Guard/Retry.
  - Fix geplant:
    - Codes länger machen (z. B. `random_bytes(8)` → 16 Hex-Zeichen) **und**
    - Unique-Index in MongoDB auf `registration_codes.code` setzen **und**
    - beim Generieren Duplicate-Key abfangen und neu generieren (Retry/Backoff).

- Einladungscodes – Fix umgesetzt (2026-04-24):
  - Code-Länge auf 16 Hex-Zeichen erhöht (`random_bytes(8)`), damit Kollisionen praktisch nicht mehr auftreten.
  - Beim Generieren wird ein Unique-Index auf `registration_codes.code` best-effort sichergestellt (Legacy-DBs).
  - Duplicate-Key (`11000`) wird abgefangen und automatisch neu generiert (Retry), statt dass der Admin beim Generieren einen Fehler sieht.

- Copy-UI Abstände/Margins:
  - Auf der Account-Seite (`index.php?page=account`) wirken die Abstände zwischen Copy-Feld und Copy-Button aktuell teils „zu eng“ bzw. inkonsistent.
  - Ursache ist sehr wahrscheinlich ein Zusammenspiel aus Legacy-Defaults (z. B. globale `button`-Regeln) und dem neuen React/Tailwind-Layout.
  - Fix geplant: CopyField/CopyButton Spacing finalisieren (Layout + ggf. alte CSS-Regeln entschärfen), sodass links/rechts konsistent „Luft“ vorhanden ist.

- Responsive Design (allgemein):
  - Es fehlt noch ein gezielter Responsive-Pass (Mobile/Tablet/Desktop), insbesondere für Navigation, Tabellen (Account/Admin), Grid/Detail-Ansichten und Lightbox/Modals.
  - Fix geplant: Breakpoints definieren und Seiten nacheinander durchgehen (Layout, Touch Targets, Textgrößen, Overflow).

- React-native Styling: CSS-Struktur aufteilen
  - Aktuell liegt der Großteil der page-scoped Styles gesammelt in `frontend/src/app.css`.
  - Fix geplant: Aufteilung in mehrere Dateien (z. B. `styles/tokens.css`, `styles/layout.css`, `styles/pages/*`, `styles/admin.css`) und zentraler Import über `app.css`/`main.tsx`, um Wartbarkeit und Merge-Konflikte zu verbessern.

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
- Responsive Pass, 2FA, Sicherheits-Review und Deployment: parallel zu `security_roadmap.md` und den offenen UI-Punkten oben.

## Nächster sinnvoller Einstieg

Beim nächsten Arbeitsstand zuerst prüfen:

1. Mit welchen effektiven Verbindungsdaten der PHP-Serverprozess wirklich läuft.
2. Ob `ADMIN_DB_URI` im laufenden Prozess tatsächlich `admin@portfolio_db` mit dem richtigen Passwort verwendet.
3. Ob `03_db_init_mongo_roles.php` nach einem sauberen Neustart des Servers mit explizit gesetzten URIs weiterhin `Authentication failed` oder `not authorized` liefert.

## Betroffene Dateien

- `includes/db.php`
- `includes/bootstrap.php`
- `pages/admin/index.php`
- `dbScripts/00_db_init_accounts.php`
- `dbScripts/03_db_init_mongo_roles.php`
- `dbScripts/db_init_master.php`
