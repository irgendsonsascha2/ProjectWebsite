# Lokales Mail-Setup (MailHog)

Ein Posteingang für **klassische PHP-App** und **Laravel** in der Testumgebung.

| App | Env-Datei | Variablen |
|-----|-----------|-----------|
| PHP (Registrierung, Invite) | Projektroot `.env.local` | `MAIL_SMTP_HOST`, `MAIL_SMTP_PORT` |
| Laravel (Passwort vergessen) | `laravel/.env` | `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT` |

Vorlagen: [`.env.local.example`](../.env.local.example), [`.env.example`](../.env.example), [`laravel/.env.example`](../laravel/.env.example).

## 1. MailHog starten

```bash
make services-up
# oder nur MailHog:
make mailhog
```

Prüfen:

```bash
make mailhog-check
```

| Dienst | URL / Port |
|--------|------------|
| Web-UI | http://127.0.0.1:8025/ |
| SMTP | 127.0.0.1:1025 |

## 2. Konfiguration (zwei Dateien)

**Projektroot** `.env.local` (oder `cp .env.local.example .env.local`):

```env
MAIL_SMTP_HOST=127.0.0.1
MAIL_SMTP_PORT=1025
MAIL_FROM_EMAIL=no-reply@localhost
```

Kein `MAIL_SMTP_ENCRYPTION` für MailHog (Plain SMTP).

**Laravel** `laravel/.env` (nach Kopie von `laravel/.env.example`):

```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS=no-reply@localhost
```

Alternative nur für Laravel: `MAIL_MAILER=log` — Reset-Link dann in `laravel/storage/logs/laravel.log`, **nicht** in MailHog.

## 3. Server neu starten

Nach `.env`-Änderungen:

```bash
# PHP-Server neu (make php)
cd laravel && php artisan config:clear
# Laravel neu (make laravel)
```

## 4. Testmatrix

| Aktion | Erwartung |
|--------|-----------|
| Registrierungsanfrage | MailHog + Kopie in `logs/mail.log` (Token redigiert) |
| Admin → Invite-Code per Mail | MailHog |
| Login → Passwort vergessen → E-Mail eingeben | MailHog (nicht `logs/mail.log`) |
| Passwort speichern + Handoff | wie nach normalem Login |

**URL:** Formular nur unter `/reset-password/{token}?email=…` (Link aus der Mail). `/reset-password` allein leitet zur Anforderungsseite um.

## Troubleshooting

| Symptom | Ursache / Fix |
|---------|----------------|
| „We have emailed your password reset link“, MailHog leer | `MAIL_MAILER=log` in `laravel/.env` → auf `smtp` stellen |
| PHP-Mails nur in `mail.log`, nicht MailHog | `MAIL_SMTP_HOST` in `.env.local` fehlt; PHP neu starten |
| `make mailhog-check` schlägt fehl | `make services-up` oder `make mailhog` |
| Reset scheitert mit Fehler zur E-Mail | Mehrere `users` mit gleicher E-Mail in Mongo |
| Erfolg, aber keine Mail | E-Mail nicht in `users`; Laravel zeigt trotzdem oft Erfolg (Enumeration-Schutz) |

Admin **Deploy-Status** zeigt lokal einen Hinweis, wenn Laravel noch `MAIL_MAILER=log` nutzt.
