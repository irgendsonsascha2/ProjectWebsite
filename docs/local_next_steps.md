# Sinnvolle Aufgaben (lokal, ohne Deployment)

Arbeitsliste für Entwicklung auf der eigenen Maschine. Reihenfolge: grober Nutzen, wenig externe Voraussetzungen.

1. **E‑Mail-Dev-Setup** — Lokalen SMTP-Test-Posteingang (z. B. [MailHog](https://github.com/mailhog/MailHog)) anbinden: in `.env.local` `MAIL_SMTP_HOST=127.0.0.1` und `MAIL_SMTP_PORT=1025` (oder wie bei deinem Dienst), MailHog/Container muss dabei laufen. `includes/mail.php` nutzt dann SMTP statt `mail()`. Sensible Werte in `logs/mail.log` werden standardmäßig redigiert (`MAIL_LOG_REDACT_SECRETS=0` für vollen Debug-Body). Beispiel (Docker): `docker run --rm -p 1025:1025 -p 8025:8025 mailhog/mailhog` — Web-UI: `http://127.0.0.1:8025/`. Details: `README.md` (Abschnitt Registrierung / Mail).

2. **Lokalen Lauf bündeln** — Ein `Makefile` oder Skript, das typische Befehle bündelt: MongoDB starten, `cd frontend && npm run build` bzw. `dev`, `php -S 127.0.0.1:8080 -t .` mit festen Ports (127.0.0.1, nicht `localhost` mischen), siehe `AGENTS.md`.

3. **Smoke-Checkliste (lokal)** — Kurz dokumentierter Ablauf: Registrierungsanfrage → Admin-Freigabe → Register → Login → Projekt anlegen → Medien hochladen → Like/Kommentar. Hilft, Regressionen früh zu sehen.

4. **Fehlerbild & Logging in Dev** — Klar sichtbare Fehlermeldungen, einheitliches Log-Ziel; keine Secrets in Logausgaben.

5. **Security (Code, lokal testbar)** — CSRF auf mutierende Formulare, Session-Cookie-Flags sinnvoll setzen, einfache Rate-Limits (z. B. pro Session/IP) für heikle Aktionen.

6. **Upload-Härtung (lokal testbar)** — Strikte MIME/Größe-Checks, sichere Dateinamen, sinnvolle Fehlermeldungen; optional Thumbnails/Resize später.

7. **UX in Grid/Detail (lokal)** — Suche/Filter/Tags, bessere Leer-Zustände, Medien-Navigation, klare Entwurf/Veröffentlicht-Zustände.

8. **Rechte: UI + Server** — Sicherstellen, dass verbotene Aktionen serverseitig geblockt werden (nicht nur UI), klare 403-Seiten.

9. **Laravel-Integration „ordentlich“ vervollständigen** — Lokal klarer Start/Stop & saubere Einbindung in die klassische Site:
   - Passwort-Reset („Passwort vergessen“) soll nicht „kaputt“ wirken, wenn Laravel nicht läuft (Link/Hint/Flow konsistent).
   - Laravel lokal starten (z. B. `cd laravel && php artisan serve --host 127.0.0.1 --port 8000`) und notwendige Migrations einmal ausführen (`php artisan migrate`, u. a. Reset-Tokens).
   - Konfiguration/Handoff sauber dokumentieren (`laravel/.env`: `APP_URL`, `HANDOFF_SECRET`, `LEGACY_SITE_URL`), damit Login/Register/Reset aus einem Guss funktionieren.

10. **Produktionsreifer Mailversand (TLS)** — umgesetzt in `includes/mail.php` (`MAIL_SMTP_ENCRYPTION`, Auth); Deploy-Status prüft Plain-SMTP in `APP_ENV=production`. Vorlage: `.env.production.example`, `docs/deployment.md`.

11. **Dokumentation für häufige Stolpersteine** — Ports, Vite-HMR, `.env.local`, Mail, Laravel — bei Bedarf in `README.md` oder hier ergänzen.

---

*Diese Datei ist eine interne Wunsch-/Prioritätenliste, nicht ein verbindliches Produktspezifikat.*
