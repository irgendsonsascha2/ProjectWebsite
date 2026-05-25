<?php

/**
 * Router für den PHP-Entwicklungsserver: Medien unter content/ nur über Auth-Proxy; logs/ blockiert.
 */

$uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$path = parse_url($uri, PHP_URL_PATH);
if (! is_string($path)) {
    $path = '/';
}

if (preg_match('#^/logs(?:/|$)#', $path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not Found';

    return true;
}

if (preg_match('#^/content/(images|videos|tmp)/.+#', $path)) {
    require __DIR__.'/media.php';

    return true;
}

return false;
