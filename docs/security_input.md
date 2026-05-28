# Input-Sicherheit (NoSQL / Struktur)

Kurzüberblick nach Security-Review der klassischen PHP-Site + Laravel-Auth-Brücken.

## Prinzip

1. **Keine** rohen `$_GET`/`$_POST`-Bäume als Mongo-Filter oder `$set`-Dokument.
2. **Typisieren** vor der Query: `ObjectId`, Whitelist-Strings, `input_enum`, serverseitige Operatoren (`$in`, `$ne`, …).
3. **Struktur** bei Listen aus Formularen: `mongo_guard_scalar_list`, `normalize_permission_keys` mit DB-Whitelist.

## Schichten

| Datei | Rolle |
|-------|--------|
| [`includes/request.php`](../includes/request.php) | Skalare aus GET/POST; keine Array-Werte für Einzelfelder |
| [`includes/input_validate.php`](../includes/input_validate.php) | Text, Enums, `input_user_password` / `input_secret_password` |
| [`includes/mongo_input_guard.php`](../includes/mongo_input_guard.php) | Safe Keys, Listen-Caps, `req_get_token_hex`, `req_post_objectid_list`, Rollen-Permission-Normalisierung |

## Behobene Risiken (Auswahl)

| Risiko | Fix |
|--------|-----|
| Privilege Escalation über `permissions[delete_all]=1` (Array-**Keys**) | `normalize_permission_keys` nutzt **Werte** + Whitelist aus `permissions_config` |
| Invite-Code beliebige Rolle | `target_role` nur `input_enum` gegen erlaubte Rollen |
| Inkonsistente Projekt-IDs | `req_get_objectid` auf `edit_project` |
| Token-Raten/Format | `req_get_token_hex` (64 Hex) für Registrierungs-Verify |
| Bulk-Delete / Reorder DoS | `req_post_objectid_list` (max 100), `input_post_int_index_list` (max 200) |

## Passwörter

| Kontext | Regel |
|---------|--------|
| Nutzer (Laravel Register/Reset) | `Password::min(12)->max(512)`, `confirmed`, kein Symbol-Zwang |
| Login / Admin-Reauth | nur Hash-Prüfung |
| Mongo/Seed (`dbScripts`) | `input_secret_password` (8–512, nur NUL verboten) |

Details: [input_validation.md](input_validation.md), [README.md](../README.md).

## Bewusst nicht

- Globales „mongo_sanitize“ auf jedem POST-Feld (Performance, falsche Sicherheit ohne typisierte Queries).
- Verschärfung von `post_max_size` für Medien (Upload-Limits bleiben in `validate_media_upload()`).
