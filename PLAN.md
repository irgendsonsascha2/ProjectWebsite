## Nächste Schritte (Notiz)

- Admin-Zugangsprozess
  - Anfrage/Workflow an Admin einrichten, um Zugangsdaten / Account-Freigabe zu bekommen

- Registrierungscode-Anfragen per E-Mail (Invite Flow)
  - Mailversand/SMTP-Konfiguration sauber machen (aktuell nutzt der Prototyp PHP `mail()` + `MAIL_FROM_EMAIL`)
  - E-Mail-Templates/Betreff/Absender später konfigurierbar machen

- Responsive Design (nach Invite-Code-Fix, vor Smoke-Test)
  - Mobile/Tablet/Desktop-Pass für Navigation, Account/Admin-Tabellen, Grid/Detail, Lightbox/Dialogs

- 2FA
  - Zwei-Faktor-Authentifizierung ergänzen (z. B. TOTP)

- Startseite als DB-Datensatz
  - Startseiten-Inhalt als Datensatz in der Datenbank speichern
  - Admin kann Startseite im UI bearbeiten (CRUD/Editor)

- Sicherheitschecks
  - Review: Berechtigungen, CSRF, Rate Limits, Input-Validation, Uploads, Session/Headers
  - Logging/Audit (falls sinnvoll)

- Deployment
  - Deployment vorbereiten und durchführen (Configs, Secrets, Backups, Rollback)

- Sonstiges
  - ggf. weitere Punkte ergänzen, wenn mir noch etwas einfällt

