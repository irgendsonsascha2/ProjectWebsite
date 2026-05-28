# Eingabevalidierung (Whitelist)

Die klassische PHP-Site validiert Benutzereingaben **serverseitig** in mehreren Schichten — kein monolithisches Filter-Skript, sondern typisierte Helfer.

## Schichten

| Schicht | Datei | Aufgabe |
|---------|--------|---------|
| HTTP-Skalare | [`includes/request.php`](../includes/request.php) | Keine Arrays/Objekte aus `$_GET`/`$_POST`, Längen, `ObjectId`, Such-Regex (`preg_quote`) |
| Text / Enums / IDs | [`includes/input_validate.php`](../includes/input_validate.php) | Whitelist für Kommentare, Projekttexte, E-Mails, Slugs, Passwörter |
| Struktur / Listen | [`includes/mongo_input_guard.php`](../includes/mongo_input_guard.php) | Operator-sichere Keys, POST-Listen, Token-Hex, Rollen-Permissions |
| Medien (Binär) | [`includes/bootstrap.php`](../includes/bootstrap.php) | `validate_media_upload()`, Extension-Whitelist, `getimagesize`, Video-Header |

**Lade-Reihenfolge** (in `bootstrap.php`): `request.php` → `input_validate.php` → `mongo_input_guard.php`.

**Ausgabe:** HTML weiter mit `htmlspecialchars` escapen — die Input-Validierung ersetzt kein XSS-Encoding.

**Laravel-Auth:** Login/Register/Reset über Laravel `Password::min(12)->max(512)` + `confirmed`; Brücken nutzen dieselbe Validierung.

## Wichtige Helfer (`input_validate.php`)

- `input_comment_text()` — Länge aus `COMMENT_TEXT_MAX_LENGTH`, Steuerzeichen gefiltert, Zeilenumbrüche erlaubt
- `input_project_title()` / `input_project_description()` / `input_project_tags()`
- `input_email()` — `filter_var(FILTER_VALIDATE_EMAIL)`
- `input_identifier_key()` — Rollen/Berechtigungen (`a-z` Start, `a-z0-9_-`, Länge)
- `input_enum()` — strikte Auswahl aus erlaubter Liste
- `input_object_id_hex()` — 24 Hex-Zeichen + gültige `ObjectId`
- `input_interaction_type()` — nur `like` / `dislike`
- `input_db_script_basename()` — nur existierende `dbScripts/NN_*.php` oder `db_init_master.php`
- `input_user_password()` — Nutzerpasswort: min/max Länge (UTF-8), **kein Trim**, Unicode erlaubt
- `input_secret_password()` / `input_password_secret()` — Mongo-/Seed-Passwörter: 8–512 Zeichen, nur NUL (`\0`) verboten
- `input_clamped_int()` — Ganzzahl mit Min/Max

## Wichtige Helfer (`mongo_input_guard.php`)

- `mongo_guard_is_safe_key()` — keine Keys mit `$` oder `.`
- `mongo_guard_scalar_list()` — Werte aus `foo[]`-Arrays, max. Anzahl
- `normalize_permission_keys($raw, $allowedFromDb)` — Checkbox-**Werte** gegen `permissions_config`
- `normalize_role_keys_list($raw, $allowedRoles)` — analog für `comment_delete_roles[]`
- `req_get_token_hex()` — feste Länge, `ctype_xdigit`
- `req_post_objectid_list()` — Bulk-IDs (z. B. Projekt-Löschen, max. 100)
- `req_post_action()` — POST `action` nur aus erlaubter Liste
- `input_post_int_index_list()` — Gallery-Reorder (max. 200 Indizes)

## dbScripts mit Formulareingaben

Nutze [`dbScripts/_script_input_helpers.php`](../dbScripts/_script_input_helpers.php):

- `db_script_resolve_password($key, $envVar)` — Passwort aus `$GLOBALS['dbScriptInput']` oder Env, validiert via `input_secret_password`
- `db_script_input_script_name()` — Whitelist für Skriptdateinamen
- `db_script_scalar_string()` — nur Skalar-Strings aus `dbScriptInput`

Admin-UI [`pages/admin/db_scripts.php`](../pages/admin/db_scripts.php) füllt `dbScriptInput` nur mit validierten Werten (E-Mail, Username-Slug, Passwörter).

## Checkliste für neue Features

Bei **neuen `pages/`**, **POST-Handlern** oder **nummerierten `dbScripts/`** mit User-Input:

1. **Keine** rohen `$_POST`/`$_GET`-Werte in Mongo-Queries oder Dateipfade.
2. IDs: `req_get_objectid()` / `input_object_id_hex()`.
3. Freitext: passender `input_*`-Helfer (Whitelist + Länge).
4. Feste Auswahlen: `input_enum()` oder `req_post_action()`.
5. Listen aus Formularen: `mongo_guard_scalar_list` / `req_post_objectid_list`.
6. Uploads: `validate_media_upload()` (kein reines Vertrauen auf Extension/MIME allein).
7. dbScripts mit Passwörtern/Parametern: `_script_input_helpers.php`, nicht `trim($_POST[...])` direkt im Skript.
8. Verhalten in `README.md` dokumentieren, wenn sich Grenzen für Nutzer ändern.

## Grenzen

- Kein HTML-Sanitizer im Input (bewusst).
- Unicode in Kommentaren/Beschreibungen und Passwörtern erlaubt.
- Medien: Whitelist + Inhaltsprüfung; gefährliche Endungen zusätzlich in `detect_media_type()` blockiert (Defense in Depth).
- PHP-Dev-Server (`serve-php.sh`): `max_input_vars=1000` gegen sehr große Formulare.

Weitere Risiko-Übersicht: [security_input.md](security_input.md).
