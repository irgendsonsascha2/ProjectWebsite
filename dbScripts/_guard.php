<?php

if (!defined('ALLOW_DB_SCRIPT_EXECUTION') || ALLOW_DB_SCRIPT_EXECUTION !== true) {
    http_response_code(403);
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    exit('Direkter Zugriff auf Datenbankskripte ist nicht erlaubt.');
}
