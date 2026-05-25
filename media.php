<?php

/**
 * Geschützte Auslieferung von Medien unter content/images und content/videos.
 */

require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/media_serve.php';

media_serve_from_request_uri();
