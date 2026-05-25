<?php

/**
 * Router für den PHP-Entwicklungsserver: Medien unter content/ nur über Auth-Proxy.
 */

$uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$path = parse_url($uri, PHP_URL_PATH);
if (! is_string($path)) {
    $path = '/';
}

if (preg_match('#^/content/(images|videos)/.+#', $path)) {
    require __DIR__.'/media.php';

    return true;
}

return false;
