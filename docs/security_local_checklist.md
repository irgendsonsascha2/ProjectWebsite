# Lokale Security-Iteration — Checkliste

**Workflow:** Agent implementiert → du testest unter „Manueller Test“ → Phase auf ✅ → nächste Phase.

**Referenz:** [security_roadmap.md](security_roadmap.md) · [next_session_plan.md](next_session_plan.md) · [security.md](security.md)

## Voraussetzungen (vor Phase 0)

- [ ] PHP-Site: `http://127.0.0.1:8080/` (`make php` oder `./serve-php.sh` — **mit router.php**)
- [ ] Laravel: `http://127.0.0.1:8000/`
- [ ] MongoDB erreichbar; `.env.local` mit `*_DB_URI`
- [ ] Test-Accounts: Admin, normaler Nutzer, ggf. Gast
- [ ] `react-dist/` gebaut (`cd frontend && npm run build`)

---

## Phase 0 — Baseline und DB-Indizes

**Status:** 🧪 Manueller Test (du)

### Implementierung (Agent)

- [x] Diese Checkliste angelegt
- [x] Hinweis: `APP_ALLOW_DEV_DB_DEFAULTS=0` + echte URIs für realistische Tests
- [x] DB-Skripte dokumentiert: `11`, `13`, `14` (Handoff), ggf. `03`, `10`

### Manueller Test (du)

- [ ] Login + Projekt-Grid funktionieren
- [ ] Handoff-Replay: zweiter Aufruf derselben Handoff-URL scheitert
- [ ] Seite hat Styling (`react-dist/`)

**Notizen / Datum:**

---

## Phase 1 — Medien-Proxy (Draft-Leak)

**Status:** 🧪 Manueller Test (du)

### Implementierung (Agent)

- [x] `includes/media_serve.php`
- [x] `media.php` + `router.php`
- [x] `content/.htaccess` (Require all denied)
- [x] `serve-php.sh` + `Makefile` mit Router
- [x] README-Hinweis Medien-Proxy

### Manueller Test (du)

- [ ] **Server neu starten** (`make php` / `./serve-php.sh`)
- [ ] Entwurfs-Projekt mit Bild: als **Gast** direkte URL `/content/images/…` → 403 oder 404
- [ ] Als **Autor** eingeloggt: Bild in Detail + direkte URL sichtbar
- [ ] Veröffentlichtes Projekt: Gast mit `view_projects` sieht Medien
- [ ] Startseiten-Portrait (falls gesetzt) öffentlich
- [ ] `js/`, `react-dist/` laden weiterhin

**Notizen / Datum:**

---

## Phase 2 — CSP ohne `script-src-attr 'unsafe-inline'`

**Status:** 🧪 Manueller Test (du)

### Implementierung (Agent)

- [x] Inline `onclick`/`onsubmit` → `data-confirm-submit`
- [x] Bestätigung in `js/csrf-forms.js` (Site + Admin)
- [x] CSP in PHP + Laravel angepasst

### Manueller Test (du)

- [ ] Response-Header: CSP **ohne** `script-src-attr 'unsafe-inline'`
- [ ] Admin: Freigeben, Rolle/Berechtigung löschen, Registrierungsfreigabe, DB-Skript-Dialog, 2FA deaktivieren — Confirm funktioniert
- [ ] Keine CSP-Fehler in der Konsole (Admin + Projektseiten)

**Notizen / Datum:**

---

## Phase 3 — Admin-Re-Auth ausweiten

**Status:** 🧪 Manueller Test (du)

### Implementierung (Agent)

- [x] `admin_reauth_form_fields()` + `admin_reauth_banner_html()` in `includes/admin_reauth.php`
- [x] Re-Auth: Nutzer-Moderation, Rolle löschen, Berechtigung löschen, Registrierungsfreigabe
- [x] Banner „Bestätigung aktiv“ auf betroffenen Admin-Seiten

### Manueller Test (du)

- [ ] Erste sensible Aktion: Passwort (+ TOTP wenn 2FA aktiv)
- [ ] Zweite Aktion innerhalb ~15 Min.: ohne erneutes Passwort
- [ ] Nach Logout / Ablauf: wieder Passwort nötig
- [ ] Falsches Passwort blockiert Aktion

**Notizen / Datum:**

---

## Phase 4 — Admin Permissions (`manage_users`)

**Status:** 🧪 Manueller Test (du)

### Implementierung (Agent)

- [x] `admin_require_manage_users()` in `pages/admin/_layout.php`
- [x] `can('manage_users')` auf users, roles, permissions, registration_requests
- [x] Admin-Nav + `index.php` Admin-Link für `admin` und `content_manager`

### Manueller Test (du)

- [ ] Nutzer ohne `manage_users`: direkte URL `pages/admin/users.php` → verweigert
- [ ] Admin mit `manage_users`: Zugriff OK
- [ ] `content_manager`: Startseite/Rechtstexte OK, keine DB-Skripte / Nutzer-Verwaltung

**Notizen / Datum:**

---

## Phase 5 — Rate-Limits (Likes + Upload)

**Status:** 🧪 Manueller Test (du)

### Implementierung (Agent)

- [x] Bucket `interaction_post` in `project_detail.php`
- [x] Bucket `media_upload` in `create_project.php` / `edit_project.php`

### Manueller Test (du)

- [ ] Viele Like-Klicks kurz hintereinander → Throttle-Hinweis
- [ ] Viele Uploads kurz hintereinander → Throttle-Hinweis
- [ ] Normaler Einzel-Upload/Like weiterhin OK

**Notizen / Datum:**

---

## Phase 6 — Upload-Härtung

**Status:** 🧪 Manueller Test (du)

### Implementierung (Agent)

- [x] Extension-Whitelist (`media_allowed_extensions`)
- [x] Bild: `getimagesize` Pflicht; Video: MIME + Header-Check

### Manueller Test (du)

- [ ] `.php` / getarnte Datei → abgelehnt
- [ ] Gültiges JPG/MP4 → OK
- [ ] Sehr große Datei → verständliche Meldung

**Notizen / Datum:**

---

## Phase 7 — Doku und Abschluss

**Status:** 🧪 Manueller Test (du)

### Implementierung (Agent)

- [x] `docs/security.md` neu
- [x] README + `security_roadmap.md` + `next_session_plan.md` Verweis
- [ ] Optional lokal: `composer audit` / `npm audit` (Checkbox unten)

### Manueller Test (du)

- [ ] Smoke: Login → Handoff → Entwurf-Medien → Admin Re-Auth → CSP-Header
- [ ] README-Abschnitt Sicherheit stimmt
- [ ] Optional: `composer audit` (Root + `laravel/`), `cd frontend && npm audit`

**Notizen / Datum:**

---

## Gesamt-Abschluss

- [ ] Alle Phasen 0–7: Manueller Test abgehakt
- [ ] Keine offenen Regressionen notiert

---

## Backlog (nicht in dieser Iteration)

- Produktions-Deploy, TLS/HSTS, SMTP-TLS (`deployment.md`)
- Permission `run_db_scripts` (DB-Skripte bleiben `admin`-only)
- Pflicht-2FA für alle Admins
