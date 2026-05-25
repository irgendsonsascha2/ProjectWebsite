# Smoke-Test (lokal oder nach Server-Deploy)

Kurz-Checkliste vor externem Setup. Automatisiert: `make deploy-check` bzw. `make prod-env-check`.

## Voraussetzungen

- [ ] `make services-up` oder Mongo erreichbar
- [ ] `make mailhog-check` OK (SMTP 127.0.0.1:1025)
- [ ] `.env.local` mit `*_DB_URI` und MailHog (`MAIL_SMTP_*`) — siehe [local_mail_setup.md](local_mail_setup.md)
- [ ] `laravel/.env`: `MAIL_MAILER=smtp`, Host/Port wie MailHog
- [ ] `make frontend-build` (react-dist/)
- [ ] PHP: `make php` (mit router.php)
- [ ] Laravel: `make laravel` (Passwort-Reset)

## Baseline (Phase 0)

- [ ] Login → Handoff → Startseite/Grid **mit Styling**
- [ ] **Handoff-Replay:** dieselbe Handoff-URL ein zweites Mal → Fehler / kein erneuter Login
- [ ] Registrierung mit Admin-Code **oder** Anfrage-Flow (E-Mail in MailHog + `logs/mail.log`)
- [ ] **Passwort vergessen:** Login → Link → E-Mail in http://127.0.0.1:8025/ → Reset → Handoff
- [ ] Projekt anlegen, Bild hochladen, Like oder Kommentar
- [ ] Admin → DB-Skripte: Re-Auth (Passwort + ggf. TOTP)
- [ ] Admin → Deploy-Status: keine roten Einträge

## Produktions-Env (Dry-Run)

```bash
make prod-env-check
```

Erwartung: Mongo-URIs aus `.env.local` gesetzt, `APP_ALLOW_DEV_DB_DEFAULTS` inaktiv; ggf. Warnung zu `SESSION_SECURE` (lokal ohne TLS normal).

## Nach Server-Deploy

- [ ] HTTPS, `SESSION_SECURE=1`, `TRUSTED_PROXY_IPS`
- [ ] `make deploy-server` oder manuell: git pull, build, migrate
- [ ] Admin Deploy-Status + ausstehende DB-Skripte
- [ ] Mail mit TLS (`MAIL_SMTP_ENCRYPTION=tls`)

Siehe auch [deployment.md](deployment.md), [security_local_checklist.md](security_local_checklist.md).
