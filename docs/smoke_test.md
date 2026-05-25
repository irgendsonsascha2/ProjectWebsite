# Smoke-Test (lokal oder nach Server-Deploy)

Kurz-Checkliste vor externem Setup. Automatisiert: `make deploy-check` bzw. `make prod-env-check`.

## Voraussetzungen

- [ ] `make services-up` oder Mongo erreichbar
- [ ] `.env.local` mit `*_DB_URI`
- [ ] `make frontend-build` (react-dist/)
- [ ] PHP: `make php` (mit router.php)
- [ ] Laravel: `make laravel` (Passwort-Reset)

## Baseline (Phase 0)

- [ ] Login → Handoff → Startseite/Grid **mit Styling**
- [ ] **Handoff-Replay:** dieselbe Handoff-URL ein zweites Mal → Fehler / kein erneuter Login
- [ ] Registrierung mit Admin-Code **oder** Anfrage-Flow (E-Mail in MailHog/logs)
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
