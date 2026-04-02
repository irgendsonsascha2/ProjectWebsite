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
- Registrierung nur über Invite-/Einmal-Codes erlauben

## Technischer Rahmen

- PHP-Anwendung ohne Framework
- Einstiegspunkt: `index.php`
- gemeinsame Initialisierung: `includes/bootstrap.php`
- Seitenlogik in `pages/`
- Admin-Funktionen in `pages/admin/`
- MongoDB als Datenbank
- Medien lokal unter `content/images` und `content/videos`

## Wichtige Dateien

- `index.php`
  - einfacher Router über `?page=...`
- `includes/bootstrap.php`
  - Session, MongoDB-Verbindung, Rollen/Rechte, Upload-Helfer
- `pages/account.php`
  - Login, Registrierung, Invite-Codes
- `pages/project_grid.php`
  - Projektübersicht
- `pages/project_detail.php`
  - Detailansicht, Likes, Dislikes, Kommentare
- `pages/create_project.php`
  - Projektanlage, Entwürfe, Medien-Uploads
- `pages/edit_project.php`
  - Projektbearbeitung
- `pages/admin/index.php`
  - Admin-Dashboard und DB-Skripte
- `pages/admin/roles.php`
  - Rollenverwaltung
- `pages/admin/permissions.php`
  - Berechtigungsverwaltung
- `dbScripts/`
  - destruktive Initialisierung und Reset von Collections
- `README.md`
  - zentrale Projektdokumentation für Menschen

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
