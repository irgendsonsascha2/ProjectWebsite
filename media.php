<?php

/**
 * Geschützte Auslieferung von Medien unter content/images, content/videos und content/tmp.
 */

require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/media_serve.php';

media_serve_from_request_uri();
