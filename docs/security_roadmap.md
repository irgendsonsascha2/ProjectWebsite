# Security Roadmap

## Aktueller Stand (Kurz)

**Stand 2026:** **Schritt 1** (kein direkter öffentlicher Webzugriff auf `dbScripts/`, Laufzeit-Guards) und **Schritt 2** (getrennte Mongo-URIs pro Rolle, Admin-Skripte nur über `ADMIN_DB_URI` / Admin-Pfad) sind in der laufenden App umgesetzt. Details: `docs/current_status.md`.

Offen bzw. nur teilweise erfüllt bleiben u. a. **Schritt 3** (zentrale Autorisierung bei allen relevanten Datenzugriffen), **2FA** und weiteres harte Maßnahmen laut Zielbild unten.

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
- Benutzerkonten müssen verpflichtend 2FA verwenden.
- Für sicherheitskritische Admin-Aktionen kann eine frische Authentifizierung verlangt werden.

## Architekturentscheidung

Die Umsetzung wird in zwei Ebenen getrennt:

### 1. Website- und Anwendungsebene

- Session- und Rollenprüfung
- E-Mail-Verifikation, 2FA-Loginfluss und Recovery
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

## Schritt 4: Datenmodell für E-Mail-Verifikation und 2FA vorbereiten

Ziel:

- Accounts können E-Mail-Verifikation und verpflichtende 2FA technisch speichern und verwalten

Umsetzung:

- User-Dokumente um Verifikations- und 2FA-Felder erweitern, z. B.:
  - `email_verified`
  - `email_verification_token`
  - `email_verification_expires_at`
  - `two_factor_enabled`
  - `two_factor_method`
  - `two_factor_totp_secret`
  - `two_factor_email_enabled`
  - `two_factor_backup_codes`
  - `two_factor_confirmed_at`
- Initialisierungsskripte und Seed-Strukturen anpassen
- zentrale Helper für Verifikationsstatus, 2FA-Status und Recovery vorbereiten

Bevorzugte Faktoren:

- TOTP-basierte 2FA mit Authenticator-App
- E-Mail-basierter Faktor an die bestätigte Anmelde-E-Mail
- Backup-Codes als Recovery-Pfad

Was danach testbar ist:

- neue und bestehende Accounts unterstützen die neuen Verifikations- und 2FA-Felder
- Datenbankstruktur ist für E-Mail-Verifikation und 2FA vorbereitet

## Schritt 5: E-Mail-Verifikation und 2FA-Einrichtung im Accountbereich aktivieren

Ziel:

- neue Benutzer müssen ihre E-Mail bei der ersten Anmeldung bestätigen
- Benutzer richten verpflichtend mindestens einen zweiten Faktor ein

Umsetzung:

- Registrierungsfluss um E-Mail-Bestätigung erweitern
- nach erstem Passwort-Login in einen Onboarding-Zustand leiten
- TOTP-Secret erzeugen und QR-/Setup-Daten anzeigen
- E-Mail-Faktor vorbereiten und Testcode an die bestätigte Adresse senden
- Benutzer mindestens einen 2FA-Faktor bestätigen lassen
- Backup-Codes erzeugen und speichern

Was danach testbar ist:

- Anmeldung ist vor bestätigter E-Mail nicht vollständig freigeschaltet
- TOTP kann eingerichtet werden
- E-Mail-Faktor kann eingerichtet werden
- falsche TOTP-Codes werden abgewiesen
- Backup-Codes werden erzeugt und angezeigt

## Schritt 6: Loginfluss um 2FA-Challenge erweitern

Ziel:

- alle Accounts benötigen nach Passwortprüfung einen verpflichtenden zweiten Faktor

Umsetzung:

- Login in zwei Phasen aufteilen:
  - Passwortprüfung
  - 2FA-Challenge
- Session erst nach erfolgreicher E-Mail-Verifikation und 2FA vollständig freischalten
- TOTP und E-Mail als Challenge-Optionen unterstützen
- Backup-Codes als Fallback zulassen

Was danach testbar ist:

- Login mit Passwort allein reicht nie aus
- Login verlangt einen gültigen zweiten Faktor
- falscher Code verhindert den Abschluss des Logins
- Backup-Code funktioniert einmalig als Recovery

## Schritt 7: Frische Admin-Authentifizierung für sensible Aktionen

Ziel:

- besonders kritische Aktionen brauchen mehr als nur eine alte Session

Umsetzung:

- Re-Auth-Fenster für Admin-Aktionen einführen
- vor destruktiven DB-Aktionen erneute Passwortbestätigung verlangen
- falls 2FA aktiv ist, zusätzlich 2FA-Code verlangen

Was danach testbar ist:

- Admin kann normale Seiten nutzen
- DB-Reset oder ähnliche Aktionen verlangen frische Bestätigung
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
4. Datenmodell für E-Mail-Verifikation und 2FA vorbereiten
5. E-Mail-Verifikation und 2FA-Einrichtung im Accountbereich aktivieren
6. Loginfluss um E-Mail-Verifikation und 2FA ergänzen
7. Frische Admin-Authentifizierung ergänzen
8. Dokumentation vervollständigen

## Offene fachliche Entscheidungen

Bestätigte Entscheidungen:

- TOTP-Apps werden unterstützt.
- E-Mail-basierte Bestätigung und E-Mail-Faktor werden unterstützt.
- Bei der ersten Anmeldung ist eine E-Mail-Bestätigung erforderlich.
- Backup-Codes sind verpflichtender Teil der Recovery-Strategie.
- 2FA ist für alle Benutzer verpflichtend und nicht optional.

## Einordnung: Code-Aufräumen vor Security-Fixing

**Vor** der gezielten Abarbeitung der nummerierten Security-Schritte (insbesondere bevor weitere Sicherheits- und Rechte-Logik tief in die Anwendung gezogen wird) ist vorgesehen, **Code-Aufräumen** zu betreiben: technische Schulden reduzieren, Struktur und Duplikate verkleinern, Konfiguration und Abgrenzung zwischen Laravel- und Legacy-Teil klarer ziehen, lesbare Grenzen und Tests dort festziehen, wo es Security später erleichtert. Ziel ist, die Security-Änderungen auf einer **stabileren, nachvollziehbareren Basis** zu machen und unnötige Merge-Konflikte / Seiteneffekte zu vermeiden. (Details, was genau in welcher Reihenfolge aufgeräumt wird, wird in der praktischen Planung mit dem Codebestand festgelegt; nicht zuletzt überschneidet sich das mit `docs/current_status.md` → Frontend-/CSS-Struktur.)
