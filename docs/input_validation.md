# Eingabevalidierung (Whitelist)

Die klassische PHP-Site validiert Benutzereingaben **serverseitig** in drei Schichten — kein monolithisches Filter-Skript, sondern typisierte Helfer.

## Schichten

| Schicht | Datei | Aufgabe |
|---------|--------|---------|
| HTTP-Skalare | [`includes/request.php`](../includes/request.php) | Keine Arrays/Objekte aus `$_GET`/`$_POST`, Längen, `ObjectId`, Such-Regex (`preg_quote`) |
| Text / Enums / IDs | [`includes/input_validate.php`](../includes/input_validate.php) | Whitelist für Kommentare, Projekttexte, E-Mails, Slugs, Admin-Labels, DB-Skriptnamen |
| Medien (Binär) | [`includes/bootstrap.php`](../includes/bootstrap.php) | `validate_media_upload()`, Extension-Whitelist, `getimagesize`, Video-Header |

**Ausgabe:** HTML weiter mit `htmlspecialchars` escapen — die Input-Validierung ersetzt kein XSS-Encoding.

**Laravel-Auth:** Login/Register über Laravel FormRequests; Brücken nutzen Laravel-Validierung.

## Wichtige Helfer (`input_validate.php`)

- `input_comment_text()` — Länge aus `COMMENT_TEXT_MAX_LENGTH`, Steuerzeichen gefiltert, Zeilenumbrüche erlaubt
- `input_project_title()` / `input_project_description()` / `input_project_tags()`
- `input_email()` — `filter_var(FILTER_VALIDATE_EMAIL)`
- `input_identifier_key()` — Rollen/Berechtigungen (`a-z` Start, `a-z0-9_-`, Länge)
- `input_enum()` — strikte Auswahl aus erlaubter Liste
- `input_object_id_hex()` — 24 Hex-Zeichen + gültige `ObjectId`
- `input_interaction_type()` — nur `like` / `dislike`
- `input_db_script_basename()` — nur existierende `dbScripts/NN_*.php` oder `db_init_master.php`
- `input_password_secret()` — min. Länge, keine Steuerzeichen/Zeilenumbrüche (Mongo-/Seed-Passwörter)
- `input_clamped_int()` — Ganzzahl mit Min/Max

## dbScripts mit Formulareingaben

Nutze [`dbScripts/_script_input_helpers.php`](../dbScripts/_script_input_helpers.php):

- `db_script_resolve_password($key, $envVar)` — Passwort aus `$GLOBALS['dbScriptInput']` oder Env, validiert
- `db_script_input_script_name()` — Whitelist für Skriptdateinamen
- `db_script_scalar_string()` — nur Skalar-Strings aus `dbScriptInput`

Admin-UI [`pages/admin/db_scripts.php`](../pages/admin/db_scripts.php) füllt `dbScriptInput` nur mit validierten Werten (E-Mail, Username-Slug, Passwörter).

## Checkliste für neue Features

Bei **neuen `pages/`**, **POST-Handlern** oder **nummerierten `dbScripts/`** mit User-Input:

1. **Keine** rohen `$_POST`/`$_GET`-Werte in Mongo-Queries oder Dateipfade.
2. IDs: `req_get_objectid()` / `input_object_id_hex()`.
3. Freitext: passender `input_*`-Helfer (Whitelist + Länge).
4. Feste Auswahlen: `input_enum()`.
5. Uploads: `validate_media_upload()` (kein reines Vertrauen auf Extension/MIME allein).
6. dbScripts mit Passwörtern/Parametern: `_script_input_helpers.php`, nicht `trim($_POST[...])` direkt im Skript.
7. Verhalten in `README.md` dokumentieren, wenn sich Grenzen für Nutzer ändern.

## Grenzen

- Kein HTML-Sanitizer im Input (bewusst).
- Unicode in Kommentaren/Beschreibungen erlaubt (kein „nur ASCII“).
- Medien: Whitelist + Inhaltsprüfung; gefährliche Endungen zusätzlich in `detect_media_type()` blockiert (Defense in Depth).
