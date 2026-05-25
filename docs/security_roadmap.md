# Security Roadmap

## Aktueller Stand (Kurz)

**Stand 2026:** **Schritt 1** (kein direkter öffentlicher Webzugriff auf `dbScripts/`, Laufzeit-Guards) und **Schritt 2** (getrennte Mongo-URIs pro Rolle, Admin-Skripte nur über `ADMIN_DB_URI` / Admin-Pfad) sind in der laufenden App umgesetzt. Details: `docs/current_status.md`.

Offen: Deployment (Server). UI/CSP-Aufräumen (2026-05-21): erledigt — `docs/next_session_plan.md`.

**Lokale Security-Iteration (2026-05):** Medien-Proxy, CSP ohne `script-src-attr`, erweiterte Admin-Re-Auth, `manage_users`-Gates, Rate-Limits Likes/Upload, Upload-Härtung — siehe `docs/security.md` und abhakbare Tests in `docs/security_local_checklist.md`.

**Security Follow-up (2026-05):** Tmp-Medien über `media_serve` (`content/tmp/`), Block `/logs`, Skript `15_db_init_security_baseline.php`, Re-Auth auf weiteren Admin-Seiten (Dialog-UX). Manuell abgehakt: Checkliste Phasen 8–11.

**Ergänzung (Sprint 1, 2026-05):** CSRF für Legacy-POSTs, gehärtete Session-Cookies, Handoff `session_regenerate`, POST-Logout, eingeschränktes `?debug=1`, keine stillen Mongo-Default-URIs ohne `APP_ALLOW_DEV_DB_DEFAULTS` — Details `docs/current_status.md`.

**Ergänzung (Sprint 2, 2026-05):** Mongo-RBAC ohne `users`/`registration_codes` für App-Rollen; `registration_code_requests` für Gäste; `includes/user_db.php`; `10_db_init_projects_indexes.php` — Details `docs/current_status.md`. Schritt 2 (URIs) war bereits umgesetzt; RBAC-Verfeinerung ist der Sprint-2-Teil.

**Ergänzung (Sprint 3, 2026-05):** Zentrale Legacy-Autorisierung (`includes/authz.php`), Session-Sync aus DB, POST-Guards auf Projektseiten, IP-Rate-Limit für Code-Anfragen. **Kein** Laravel-`/verify-email`-Zwang mehr; Invite-Registrierung setzt `email_verified_at`. Schritt 3 weitgehend umgesetzt; optional 2FA = Schritt 4 (neu, siehe unten).

**Ergänzung (Sprint 5, 2026-05):** Admin-Nutzer-Moderation (`pages/admin/users.php`, `includes/user_moderation.php`): Timeout/Ban mit optional sichtbarem Grund; Durchsetzung über Brücken, Handoff und Session-Sync. `registration_codes`-Schreibzugriff nur über Admin-Mongo-URI.

## Ausgangslage (früherer Ist-Stand, teilweise inzwischen adressiert)

Folgendes war zu verschiedenen Zeiten sichtbar; die ersten Punkte betreffen die jetzige Codebasis **nicht mehr** in derselben Form:

- ~~`dbScripts/db_init_master.php` war direkt per URL ausführbar~~ → mit Guard + Admin-Pfad ersetzt (siehe `current_status.md`).
- ~~Initialisierungs- und Reset-Skripte ohne saubere Admin-Einbettung~~ → nur noch mit Guard / privilegiertem Kanal.
- Berechtigungen: stark in der Website-Logik, **noch nicht überall** in einer zentralen Datenzugriffsschicht (weiter: Schritt 3).
- Accounts haben **noch keine** Zwei-Faktor-Authentifizierung.

Da sich die Datenbank noch im Aufbau befindet, dürfen Struktur und Initialisierungslogik angepasst werden, auch wenn dafür bestehende Collections oder Seed-Strukturen geändert werden müssen.

## Zielbild

Am Ende der Umsetzung soll gelten:

- Datenbankskripte sind nicht mehr öffentlich direkt ausführbar.
- Destruktive oder administrative Datenbankaktionen sind nur nach echter Administrator-Authentifizierung möglich.
- Die Anwendungsdatenbank läuft mit technisch eingeschränkten Rechten.
- Allgemeine Nutzerrechte werden nicht nur in der Oberfläche, sondern in der zentralen Datenzugriffslogik erzwungen.
- Benutzer können **optional** TOTP-2FA in der klassischen PHP-App aktivieren (nicht verpflichtend).
- E-Mail-Nachweis nur im **Registrierungs-Anfrage-Flow**, nicht als Laravel-Login-Gate.
- Für sicherheitskritische Admin-Aktionen (DB-Skripte) ist frische Passwort-(+ optional 2FA-)Bestätigung mit 15-Min.-Fenster umgesetzt (`includes/admin_reauth.php`).

## Architekturentscheidung

Die Umsetzung wird in zwei Ebenen getrennt:

### 1. Website- und Anwendungsebene

- Session- und Rollenprüfung
- Registrierungs-E-Mail (Anfrage-Flow), optionales TOTP-2FA und Recovery
- zentrale Autorisierungs- und Datenzugriffsschicht
- Admin-Oberflächen

### 2. Datenbank- und Betriebs-Ebene

- getrennte DB-User pro Website-Rolle, z. B. `viewer`, `community_member`, `content_manager`
- `admin` als privilegierter DB-User für Admin-Betrieb, Wartung, Initialisierung und Schemaänderungen
- Datenvalidierung, Indizes und technische Schutzmechanismen in MongoDB

Wichtig: MongoDB-Rollen sind technisch sinnvoll für grobe Rechte auf Datenbank- und Collection-Ebene. Feine fachliche Regeln wie Ownership, Upload-Berechtigung oder konkrete Aktionsfreigaben müssen zusätzlich zentral in der Anwendungslogik durchgesetzt werden. Die technische Datenbankberechtigung schützt zusätzlich vor zu weitreichenden Operationen wie `dropCollection`, `createCollection` oder globalen Resets.

## Geplante Umsetzung in testbaren Schritten

## Schritt 1: Öffentliche DB-Skriptausführung schließen

**Status: umgesetzt** (siehe `docs/current_status.md`).

Ziel:

- `dbScripts/*.php` sollen nicht mehr direkt per URL ausführbar sein.
- Initialisierungsskripte sollen nur noch über einen kontrollierten Admin-Pfad nutzbar sein.

Umsetzung:

- direkten Webzugriff auf `dbScripts/` blockieren
- gemeinsame Admin-Guard-Funktion einführen
- Admin-Dashboard so umbauen, dass es Skripte nur über einen sicheren Serverpfad startet
- direkte Scriptdateien mit einem Laufzeit-Guard versehen, damit sie ohne autorisierten Kontext abbrechen

Was danach testbar ist:

- Aufruf von `dbScripts/db_init_master.php` direkt im Browser schlägt fehl.
- Ausführung über den Admin-Bereich funktioniert nur als eingeloggter Admin.

## Schritt 2: Technische Datenbankrechte trennen

**Status: umgesetzt** (getrennte URIs, Admin-Pfad für Skripte; Details `current_status.md`).

Ziel:

- der normale Website-Betrieb darf keine administrativen Datenbankoperationen ausführen

Umsetzung:

- Konfiguration für getrennte DB-Verbindungen vorbereiten:
  - `VIEWER_DB_URI`
  - `COMMUNITY_DB_URI`
  - `CONTENT_MANAGER_DB_URI`
  - `ADMIN_DB_URI`
  - `APP_DB_NAME`
- Bootstrap auf rollenbasierte DB-Verbindungsauswahl umstellen
- Admin-Skriptausführung nur mit privilegierter `admin`-Verbindung erlauben
- Initialisierungsskripte auf den privilegierten Kontext begrenzen
- dokumentieren, dass MongoDB-Authentifizierung aktiviert sein muss
- ein nummeriertes DB-Skript für MongoDB-Custom-Roles und MongoDB-Benutzer bereitstellen

Was danach testbar ist:

- normale Seiten funktionieren mit der rollenabhängigen DB-Verbindung
- administrative DB-Operationen funktionieren nicht über `viewer`, `community_member` oder `content_manager`
- Admin-Skripte laufen nur noch über den privilegierten `admin`-Kanal

## Schritt 3: Zentrale Autorisierungs- und Datenzugriffsschicht einführen

**Status: weitgehend umgesetzt** (Guards + Session-Sync; nicht jede Seite vollständig migriert).

Ziel:

- Rechte nicht nur im UI, sondern bei allen relevanten Datenzugriffen zentral erzwingen

Umsetzung:

- gemeinsame Helper für:
  - aktueller Benutzer
  - Rollen- und Rechteauflösung
  - Projektzugriff
  - Kommentarzugriff
  - Admin-only Aktionen
- schreibende und sensible lesende Zugriffe von Seitenlogik in diese Schicht ziehen
- Ownership- und Rollenregeln dort erzwingen

Beispiele:

- ein Nutzer darf nur eigene Projekte bearbeiten, wenn er nicht `edit_all` hat
- Kommentar-Löschung wird zentral nach Eigentum, Rolle und Löschrechten geprüft
- Erstellen, Löschen und Bearbeiten von Inhalten läuft immer über zentrale Guards

Was danach testbar ist:

- UI-Hiding allein reicht nicht mehr aus, direkte Requests werden serverseitig korrekt blockiert
- Rechteverletzungen schlagen auch bei manuell manipulierten Formularen fehl

## Schritt 4: Optionales TOTP-2FA (klassische PHP-App)

**Status: umgesetzt** (`includes/two_factor.php`, `bridge_auth_2fa.php`, `pages/account.php`).

Ziel:

- Nutzer können freiwillig TOTP (Authenticator-App) aktivieren; ohne 2FA bleibt Login wie heute (Passwort + Handoff).

Umsetzung:

- User-Felder z. B. `two_factor_enabled`, `two_factor_totp_secret`, `two_factor_backup_codes`, `two_factor_confirmed_at`
- Account-UI in `pages/account.php`: Setup (QR), Bestätigung, Deaktivierung, Backup-Codes anzeigen
- Login: nach erfolgreichem Passwort optional zweite Phase nur wenn `two_factor_enabled`
- **Nicht:** Laravel `/verify-email`, E-Mail als Login-2FA, verpflichtende 2FA für alle

Was danach testbar ist:

- Account ohne 2FA: Login unverändert
- Mit 2FA: Login ohne TOTP scheitert; Backup-Code einmalig nutzbar

## Schritt 5: Registrierung — `email_verified_at` aus Anfrage-Flow

**Status: umgesetzt** (`RegisterInvitedUser::resolveEmailVerifiedAt()`).

Ziel:

- Wer sich mit Code registriert und dessen E-Mail zu einer **verifizierten** `registration_code_requests`-Zeile passt, erhält konsistent `email_verified_at` (für spätere Auswertung/Anzeige, nicht für Login-Sperre).

Umsetzung:

- In `RegisterInvitedUser` oder Legacy-Bridge: Match E-Mail ↔ `verified_at` in `registration_code_requests`
- Dokumentation in README/`current_status.md`

## Schritt 6: Admin-CSRF und Re-Auth

**Status: umgesetzt.**

Ziel:

- Alle Admin-POST-Formulare mit `csrf_field()`; CSRF-Fehler bleiben auf derselben Admin-Seite
- Vor destruktiven DB-Aktionen frische Passwort-(+ optional 2FA-)Bestätigung

Umsetzung: CSRF auf Admin-POST-Formularen; Re-Auth für `pages/admin/db_scripts.php` über `includes/admin_reauth.php` (15 Min. TTL, Passwort + TOTP wenn 2FA aktiv).

## Schritt 7: Frische Admin-Authentifizierung für sensible Aktionen

**Status: umgesetzt** für DB-Skript-Ausführung (Schritt 6/7 zusammengeführt in `admin_reauth.php`).

Ziel:

- besonders kritische Aktionen brauchen mehr als nur eine alte Session

Umsetzung:

- Re-Auth-Fenster für Admin-Aktionen (900 s)
- vor destruktiven DB-Aktionen erneute Passwortbestätigung verlangen
- falls 2FA aktiv ist, zusätzlich TOTP-Code verlangen

Was danach testbar ist:

- Admin kann normale Seiten nutzen
- DB-Reset oder ähnliche Aktionen verlangen frische Bestätigung (oder gültiges Fenster)
- abgelaufene Admin-Bestätigung blockiert den Vorgang

## Schritt 8: Datenbanklogik und Dokumentation finalisieren

Ziel:

- Sicherheitslogik ist konsistent dokumentiert und nachvollziehbar

Umsetzung:

- README aktualisieren
- Architektur- und Sicherheitsdokumentation ergänzen
- ggf. zusätzliche `docs/database.md` oder `docs/security.md` anlegen

Was danach testbar ist:

- Setup und Sicherheitsmodell sind dokumentiert
- ein Entwickler kann das neue Rechte- und 2FA-Modell nachvollziehen

## Empfohlene Reihenfolge

1. Öffentliche DB-Skriptausführung schließen
2. Technische Datenbankrechte trennen
3. Zentrale Autorisierungs- und Datenzugriffsschicht einführen
4. Optionales TOTP-2FA (PHP)
5. `email_verified_at` aus Anfrage-Flow beim Register
6. Admin-CSRF vollständig + Re-Auth
7. Frische Admin-Authentifizierung für DB-Destructive (Detail Schritt 7 unten)
8. Dokumentation vervollständigen

## Offene fachliche Entscheidungen

Bestätigte Entscheidungen (Stand 2026-05, Zielbild):

- **Keine Laravel-E-Mail-Verifikation** für die klassische Site; Laravel bleibt **vorerst** nur für Login, Registrierung mit Invite-Code (`bridge_*`) und Passwort-Reset.
- **E-Mail-Nachweis bei Registrierung** nur über den bestehenden Flow: Nutzer bestätigt die **Anfrage-E-Mail** per Link (`verify_registration_request`), danach **manuelle Admin-Freigabe** (`registration_requests`), erst dann Invite-Code und Kontoanlage.
- **Kein separater Laravel-`/verify-email`-Zwang** für Legacy-Nutzer; `email_verified_at` am User wird aus dem Registrierungsflow (passende, verifizierte Anfrage) oder expliziter Legacy-Logik gesetzt — nicht über Laravel Dashboard.
- **2FA optional** (nicht verpflichtend): TOTP in **`pages/two_factor.php`**, nicht in Laravel.
- Backup-Codes als Recovery, wenn 2FA aktiv ist (empfohlen, nicht für alle Accounts Pflicht).
- E-Mail als **zweiter Faktor beim Login** ist **nicht** vorgesehen (nur TOTP optional); E-Mail dient dem Nachweis vor Registrierung und dem Versand des Invite-Codes.

Abweichung vom früheren Plan: Schritt 5–6 der Roadmap (verpflichtende 2FA + Laravel-Verify) werden durch obiges Zielbild ersetzt.

## Ergänzung (Aufräumen + Security-Pass, 2026-05)

Umgesetzt in einer Session (Code + Härtung):

- Toter Code entfernt (`js/theme-toggle.js`, `pages/account.php` Code-Generator, ungenutzte `authz`-Stubs, React `Card`/Imports).
- `includes/registration_codes.php`, `legacy_index_url()` für Invite-Links.
- **SVG** aus Upload-Typen entfernt (`includes/bootstrap.php`).
- **Handoff:** Einmal-Nonce (`handoff_tokens`, `dbScripts/14_db_init_handoff_tokens.php`, HMAC `uid|exp|nonce`).
- **Security-Header** (`includes/security_headers.php`, Laravel `SecurityHeaders`-Middleware).
- **Rate-Limits:** Kommentare (`comment_post`), fehlgeschlagene Handoffs (`handoff_fail`).
- **Output:** `$message` überwiegend mit `htmlspecialchars` (Moderation-HTML nur explizit).
- **Laravel slim:** Verify-Email, Dashboard, Profil entfernt; Hybrid behält Reset + Brücken.
- **Docker:** Mongo/MailHog nur `127.0.0.1`.
- Dependency-Audit: siehe `docs/current_status.md` (Abschnitt Dependency-Audit).

## Einordnung: Code-Aufräumen vor Security-Fixing

**Vor** weiterer tiefer Security-Arbeit war vorgesehen, **Code-Aufräumen** zu betreiben — der obige Pass deckt den Großteil ab. Optional offen: technische Schulden reduzieren, Struktur und Duplikate verkleinern, Konfiguration und Abgrenzung zwischen Laravel- und Legacy-Teil klarer ziehen, lesbare Grenzen und Tests dort festziehen, wo es Security später erleichtert. Ziel ist, die Security-Änderungen auf einer **stabileren, nachvollziehbareren Basis** zu machen und unnötige Merge-Konflikte / Seiteneffekte zu vermeiden. (Details, was genau in welcher Reihenfolge aufgeräumt wird, wird in der praktischen Planung mit dem Codebestand festgelegt; nicht zuletzt überschneidet sich das mit `docs/current_status.md` → Frontend-/CSS-Struktur.)
