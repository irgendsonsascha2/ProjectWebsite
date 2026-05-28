## Security-Härtung: Lokale Test-Checkliste

Diese Checkliste ist dafür gedacht, nach jeder Etappe kurz zu verifizieren, dass nichts im User-Flow kaputt ging und dass die Security-Guards greifen.

### Vorbereitungen

- Stelle sicher, dass Website + Services laufen (z. B. via `make php` / `./serve-php.sh` und eurem `startall`-Setup).
- Nutze **denselben Host** (empfohlen `127.0.0.1`), damit Cookies/Session stabil bleiben.

### Baseline (vor Etappe 1)

- **Auth**
  - Login funktioniert.
  - Logout funktioniert.
  - Registrierung (falls aktiv) funktioniert.
- **Project Grid**
  - `/?page=project_grid` lädt.
  - Suche: `/?page=project_grid&q=test` lädt und zeigt (wenn vorhanden) Treffer.
- **Project Detail**
  - Ein Projekt öffnen: `/?page=project_detail&id=<id>`
  - Kommentar schreiben (falls Permission vorhanden).
  - Like/Dislike setzen (falls Permission vorhanden).
- **Admin (falls du Admin bist)**
  - `pages/admin/db_scripts.php` öffnet (Dialoge funktionieren).
- **Media Serve**
  - Ein Projektbild/video über `content/images/...` oder `content/videos/...` lädt (403/404 wäre ein Bug).
  - Im Edit-Flow: temp Media unter `content/tmp/...` lädt (für Projekt-Editoren).

### Security-Probes (nach Etappe 1/2)

- **NoSQL/Regex Injection-Probe (soll harmlos bleiben)**
  - `/?page=project_grid&q=.*`
  - `/?page=project_grid&q=)|(.*`
  - Erwartung: Keine Operator-Effekte, keine Errors, keine “alles matcht” Überraschungen. Nur normale Suche.
- **Type-Fuzzing**
  - `/?page=project_detail&id[]=x` (Array statt String)
  - `/?page=project_detail&id=not-a-valid-objectid`
  - Erwartung: saubere Fehlermeldung, kein Fatal/Warning.
- **Limit-Klemmung**
  - `/?page=project_detail&id=<id>&media_limit=999999`
  - Erwartung: UI bleibt stabil, keine extremen DB-Loads.

### DB-Skripte (nach Etappe 3)

- Ohne `ALLOW_DESTRUCTIVE_DB_SCRIPTS=1`:
  - Admin-UI: Versuch ein destruktives Skript (`00_…`, `01_…`, `02_…`, `05_…`, `db_init_master.php`) auszuführen → muss serverseitig verweigern.
  - CLI: `php scripts/run-db-script.php 00_db_init_accounts.php` → verweigert.
- Mit `ALLOW_DESTRUCTIVE_DB_SCRIPTS=1`:
  - Skript wird nur nach zusätzlicher Bestätigung/Confirmation akzeptiert.

### `.env.local` Update (nach Etappe 4)

- “Böses” Passwort mit Newline in Admin-DB-Skript 03 → wird abgelehnt, `.env.local` bleibt unverändert.
- Normale Passwörter → `.env.local` wird geschrieben und Website kann weiterhin starten (DB-Verbindungen ok).

