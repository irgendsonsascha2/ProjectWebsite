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

**Status:** ✅ erledigt

### Implementierung (Agent)

- [x] `includes/media_serve.php`
- [x] `media.php` + `router.php`
- [x] `content/.htaccess` (Require all denied)
- [x] `serve-php.sh` + `Makefile` mit Router
- [x] README-Hinweis Medien-Proxy
- [x] Fix: `isset($db->projects)` → Mongo-Lookup funktioniert

### Manueller Test (du)

- [x] **Server neu starten** (`make php` / `./serve-php.sh`)
- [x] Entwurfs-Projekt mit Bild: als **Gast** direkte URL `/content/images/…` → 403 oder 404
- [x] Als **Autor** eingeloggt: Bild in Detail + direkte URL sichtbar
- [x] Veröffentlichtes Projekt: Gast mit `view_projects` sieht Medien
- [x] Startseiten-Portrait (falls gesetzt) öffentlich
- [x] `js/`, `react-dist/` laden weiterhin

**Notizen / Datum:** 2026-05-25 — OK

---

## Phase 2 — CSP ohne `script-src-attr 'unsafe-inline'`

**Status:** ✅ erledigt

### Implementierung (Agent)

- [x] Inline `onclick`/`onsubmit` → `data-confirm-submit`
- [x] Bestätigung in `js/csrf-forms.js` (Site + Admin)
- [x] CSP in PHP + Laravel angepasst
- [x] Rollen/Berechtigungen: Löschen + Bearbeiten mit Re-Auth im Dialog (`admin_reauth_dialog_body`)

### Manueller Test (du)

- [x] Response-Header: CSP **ohne** `script-src-attr 'unsafe-inline'`
- [x] Admin: Freigeben, Rolle/Berechtigung löschen, Registrierungsfreigabe, DB-Skript-Dialog, 2FA deaktivieren — Confirm funktioniert
- [x] Keine CSP-Fehler in der Konsole (Admin + Projektseiten)

**Notizen / Datum:** 2026-05-25 — OK

---

## Phase 3 — Admin-Re-Auth ausweiten

**Status:** ✅ erledigt

### Implementierung (Agent)

- [x] `admin_reauth_form_fields()` + `admin_reauth_banner_html()` in `includes/admin_reauth.php`
- [x] Re-Auth: Nutzer-Moderation, Rolle löschen/bearbeiten, Berechtigung löschen/bearbeiten, Registrierungsfreigabe, DB-Skripte
- [x] Banner „Bestätigung aktiv“ auf betroffenen Admin-Seiten

### Manueller Test (du)

- [x] Erste sensible Aktion: Passwort (+ TOTP wenn 2FA aktiv)
- [x] Zweite Aktion innerhalb ~15 Min.: ohne erneutes Passwort
- [x] Nach Logout / Ablauf: wieder Passwort nötig
- [x] Falsches Passwort blockiert Aktion

**Notizen / Datum:** 2026-05-25 — OK

---

## Phase 4 — Admin Permissions (`manage_users`)

**Status:** ✅ erledigt

### Implementierung (Agent)

- [x] `admin_require_manage_users()` in `pages/admin/_layout.php`
- [x] `can('manage_users')` auf users, roles, permissions, registration_requests
- [x] Admin-Nav + `index.php` Admin-Link für `admin` und `content_manager`

### Manueller Test (du)

- [x] Nutzer ohne `manage_users`: direkte URL `pages/admin/users.php` → verweigert
- [x] Admin mit `manage_users`: Zugriff OK
- [x] `content_manager`: Startseite/Rechtstexte OK, keine DB-Skripte / Nutzer-Verwaltung

**Notizen / Datum:** 2026-05-25 — OK

---

## Phase 5 — Rate-Limits (Likes + Upload)

**Status:** ✅ erledigt

### Implementierung (Agent)

- [x] Bucket `interaction_post` in `project_detail.php`
- [x] Bucket `media_upload` in `create_project.php` / `edit_project.php`

### Manueller Test (du)

- [x] Viele Like-Klicks kurz hintereinander → Throttle-Hinweis
- [x] Viele Uploads kurz hintereinander → Throttle-Hinweis
- [x] Normaler Einzel-Upload/Like weiterhin OK

**Notizen / Datum:** 2026-05-25 — OK

---

## Phase 6 — Upload-Härtung

**Status:** ✅ erledigt

### Implementierung (Agent)

- [x] Extension-Whitelist (`media_allowed_extensions`)
- [x] Bild: `getimagesize` Pflicht; Video: MIME + Header-Check

### Manueller Test (du)

- [x] `.php` / getarnte Datei → abgelehnt
- [x] Gültiges JPG/MP4 → OK
- [x] Sehr große Datei → verständliche Meldung

**Notizen / Datum:** 2026-05-25 — OK

---

## Phase 7 — Doku und Abschluss

**Status:** ✅ erledigt

### Implementierung (Agent)

- [x] `docs/security.md` neu
- [x] README + `security_roadmap.md` + `next_session_plan.md` Verweis
- [ ] Optional lokal: `composer audit` / `npm audit` (Checkbox unten)

### Manueller Test (du)

- [x] Smoke: Login → Handoff → Entwurf-Medien → Admin Re-Auth → CSP-Header
- [x] README-Abschnitt Sicherheit stimmt
- [ ] Optional: `composer audit` (Root + `laravel/`), `cd frontend && npm audit`

**Notizen / Datum:** 2026-05-25 — OK

---

## Gesamt-Abschluss

- [x] Alle Phasen 0–7: Manueller Test abgehakt
- [x] Keine offenen Regressionen notiert

**Datum:** 2026-05-25 — Lokale Security-Iteration abgeschlossen.

---

## Phase 8 — DB Security-Baseline (Skript 15)

**Status:** 🧪 Manueller Test (du)

### Implementierung (Agent)

- [x] `dbScripts/15_db_init_security_baseline.php`
- [x] README-Verweis

### Manueller Test (du)

- [ ] Admin → DB-Skripte → `15_db_init_security_baseline.php` → Erfolg
- [ ] Skript **zweites Mal** ausführen → idempotent, kein Fehler

**Notizen / Datum:**

---

## Phase 9 — Tmp-Medien-Proxy

**Status:** 🧪 Manueller Test (du)

### Implementierung (Agent)

- [x] `router.php` + `includes/media_serve.php` für `content/tmp/`
- [x] Zugriff nur mit `authz_can_edit_project()`

### Manueller Test (du)

- [ ] `make php` neu starten
- [ ] Autor: Bild in `edit_project` hochladen → Tmp-URL kopieren
- [ ] Ausgeloggt / anderer User: Tmp-URL → **403**
- [ ] Autor: Tmp-URL → **200**
- [ ] Regression: `/content/images/…` veröffentlichtes Projekt weiterhin OK

**Notizen / Datum:**

---

## Phase 10 — logs/ blockiert

**Status:** 🧪 Manueller Test (du)

### Implementierung (Agent)

- [x] `logs/.htaccess`
- [x] `router.php` blockiert `/logs`

### Manueller Test (du)

- [ ] `http://127.0.0.1:8080/logs/handoff_errors.log` → **404**
- [ ] Login/Seite normal

**Notizen / Datum:**

---

## Phase 11 — Admin Re-Auth erweitert

**Status:** 🧪 Manueller Test (du)

### Implementierung (Agent)

- [x] Re-Auth: `invite_codes`, `home_profile`, `legal_page_edit`, `settings`
- [x] `content_manager` in `admin_reauth_load_user()`
- [x] Passwort nur im Bestätigungs-Dialog (nicht dauerhaft im Seitenformular)
- [x] Fix `home_profile`: Mongo `$set` / `$setOnInsert` Konflikt `page_kind`

### Manueller Test (du)

- [ ] Einladungscode: Passwort nötig, 15-Min.-Fenster
- [ ] Startseite / Impressum / Einstellungen: gleiches Verhalten
- [ ] Falsches Passwort blockiert
- [ ] Regression: DB-Skripte + Nutzer-Moderation

**Notizen / Datum:**

---

## Backlog (nicht in dieser Iteration)

- Produktions-Deploy, TLS/HSTS, SMTP-TLS (`deployment.md`)
- Permission `run_db_scripts` (DB-Skripte bleiben `admin`-only)
- Pflicht-2FA für alle Admins
- TOTP-Secret-Verschlüsselung in Mongo
