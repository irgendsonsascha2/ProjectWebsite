# Current Status

## Stand vom 2026-04-01

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
