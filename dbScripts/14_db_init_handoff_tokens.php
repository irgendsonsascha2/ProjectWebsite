<?php

/**
 * TTL-Index für handoff_tokens (Einmal-Nonces für Laravel-Handoff).
 */

require_once __DIR__.'/_guard.php';

require_once dirname(__DIR__).'/includes/db.php';

[, $db] = get_admin_mongo_connection();
require_once dirname(__DIR__).'/includes/handoff_tokens.php';

handoff_token_ensure_indexes($db);

echo "handoff_tokens: TTL- und Unique-Index angelegt.\n";
