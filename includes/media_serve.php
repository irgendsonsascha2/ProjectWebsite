<?php

/**
 * Geschützte Auslieferung von Dateien unter content/images, content/videos und content/tmp.
 */

require_once __DIR__.'/authz.php';
require_once __DIR__.'/mongo_collections.php';

use MongoDB\BSON\ObjectId;

if (! function_exists('media_serve_relative_path_published')) {
    function media_serve_relative_path_published(string $raw): ?string
    {
        $raw = str_replace('\\', '/', trim($raw));
        $raw = ltrim($raw, '/');
        if ($raw === '' || strpos($raw, '..') !== false) {
            return null;
        }
        if (! preg_match('#^content/(images|videos)/[a-zA-Z0-9._-]+$#', $raw)) {
            return null;
        }

        return $raw;
    }
}

if (! function_exists('media_serve_relative_path_tmp')) {
    function media_serve_relative_path_tmp(string $raw): ?string
    {
        $raw = str_replace('\\', '/', trim($raw));
        $raw = ltrim($raw, '/');
        if ($raw === '' || strpos($raw, '..') !== false) {
            return null;
        }
        if (! preg_match('#^content/tmp/[a-fA-F0-9]{24}/[a-fA-F0-9]{24}/(images|videos)/[a-zA-Z0-9._-]+$#', $raw)) {
            return null;
        }

        return $raw;
    }
}

if (! function_exists('media_serve_relative_path')) {
    /**
     * Normalisiert einen relativen Pfad (published oder tmp).
     */
    function media_serve_relative_path(string $raw): ?string
    {
        $published = media_serve_relative_path_published($raw);
        if ($published !== null) {
            return $published;
        }

        return media_serve_relative_path_tmp($raw);
    }
}

if (! function_exists('media_serve_is_tmp_path')) {
    function media_serve_is_tmp_path(string $relativePath): bool
    {
        return str_starts_with($relativePath, 'content/tmp/');
    }
}

if (! function_exists('media_serve_tmp_project_id')) {
    function media_serve_tmp_project_id(string $relativePath): ?string
    {
        if (! media_serve_is_tmp_path($relativePath)) {
            return null;
        }
        if (! preg_match('#^content/tmp/[a-fA-F0-9]{24}/([a-fA-F0-9]{24})/#', $relativePath, $m)) {
            return null;
        }

        return $m[1];
    }
}

if (! function_exists('media_serve_absolute_path')) {
    function media_serve_absolute_path(string $relativePath): ?string
    {
        $root = realpath(__DIR__.'/..');
        if ($root === false) {
            return null;
        }
        $full = realpath($root.'/'.$relativePath);
        if ($full === false || ! is_file($full)) {
            return null;
        }

        if (media_serve_is_tmp_path($relativePath)) {
            $tmpDir = realpath($root.'/content/tmp');
            if ($tmpDir !== false && strpos($full, $tmpDir) === 0) {
                return $full;
            }

            return null;
        }

        $imagesDir = realpath($root.'/content/images');
        $videosDir = realpath($root.'/content/videos');
        if ($imagesDir !== false && strpos($full, $imagesDir) === 0) {
            return $full;
        }
        if ($videosDir !== false && strpos($full, $videosDir) === 0) {
            return $full;
        }

        return null;
    }
}

if (! function_exists('media_serve_is_public_home_portrait')) {
    function media_serve_is_public_home_portrait($db, string $relativePath): bool
    {
        try {
            $doc = $db->site_pages->findOne(['_id' => 'home_profile']);
        } catch (Throwable $e) {
            return false;
        }
        if (! $doc) {
            return false;
        }
        $portrait = trim((string) ($doc['portrait_url'] ?? ''));

        return $portrait !== '' && $portrait === $relativePath;
    }
}

if (! function_exists('media_serve_find_project_for_url')) {
    /**
     * @return array<string, mixed>|null
     */
    function media_serve_find_project_for_url($db, string $relativePath): ?array
    {
        try {
            $project = mongo_projects_for_read($db)->findOne([
                '$or' => [
                    ['gallery.url' => $relativePath],
                    ['thumbnail' => $relativePath],
                ],
            ]);
        } catch (Throwable $e) {
            return null;
        }
        if (! $project) {
            return null;
        }
        if ($project instanceof Traversable) {
            return iterator_to_array($project);
        }

        return is_array($project) ? $project : null;
    }
}

if (! function_exists('media_serve_find_project_by_id')) {
    /**
     * @return array<string, mixed>|null
     */
    function media_serve_find_project_by_id($db, string $projectIdHex): ?array
    {
        if (! preg_match('/^[a-fA-F0-9]{24}$/', $projectIdHex)) {
            return null;
        }
        try {
            $project = mongo_projects_for_read($db)->findOne(['_id' => new ObjectId($projectIdHex)]);
        } catch (Throwable $e) {
            return null;
        }
        if (! $project) {
            return null;
        }
        if ($project instanceof Traversable) {
            return iterator_to_array($project);
        }

        return is_array($project) ? $project : null;
    }
}

if (! function_exists('media_serve_may_access_tmp')) {
    function media_serve_may_access_tmp($db, string $relativePath): bool
    {
        if (! authz_is_logged_in()) {
            return false;
        }
        $projectIdHex = media_serve_tmp_project_id($relativePath);
        if ($projectIdHex === null) {
            return false;
        }
        $project = media_serve_find_project_by_id($db, $projectIdHex);
        if ($project === null) {
            return false;
        }

        return authz_can_edit_project($project);
    }
}

if (! function_exists('media_serve_may_access')) {
    function media_serve_may_access($db, string $relativePath): bool
    {
        if (! function_exists('stress_mode_blocks_guest_content')) {
            require_once __DIR__.'/stress_mode.php';
        }
        if (stress_mode_blocks_guest_content()) {
            return false;
        }

        if (media_serve_is_tmp_path($relativePath)) {
            return media_serve_may_access_tmp($db, $relativePath);
        }

        if (media_serve_is_public_home_portrait($db, $relativePath)) {
            return true;
        }

        $project = media_serve_find_project_for_url($db, $relativePath);
        if ($project === null) {
            return false;
        }

        return authz_can_view_project($project);
    }
}

if (! function_exists('media_serve_content_type')) {
    function media_serve_content_type(string $absolutePath, string $relativePath): string
    {
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($absolutePath);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }
        $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'bmp' => 'image/bmp',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'ogv' => 'video/ogg',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'mkv' => 'video/x-matroska',
        ];

        return $map[$ext] ?? 'application/octet-stream';
    }
}

if (! function_exists('media_serve_send')) {
    /**
     * Liefert eine Mediendatei aus oder beendet mit 403/404.
     *
     * @param string $relativePath z. B. content/images/media_xxx.jpg
     */
    function media_serve_send(string $relativePath): void
    {
        $normalized = media_serve_relative_path($relativePath);
        if ($normalized === null) {
            http_response_code(404);
            exit;
        }

        $absolute = media_serve_absolute_path($normalized);
        if ($absolute === null) {
            http_response_code(404);
            exit;
        }

        global $db;
        if (! isset($db)) {
            require_once __DIR__.'/db.php';
        }

        if (! media_serve_may_access($db, $normalized)) {
            http_response_code(403);
            exit;
        }

        $type = media_serve_content_type($absolute, $normalized);
        header('Content-Type: '.$type);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        header('Content-Length: '.(string) filesize($absolute));
        readfile($absolute);
        exit;
    }
}

if (! function_exists('media_serve_from_request_uri')) {
    function media_serve_from_request_uri(): void
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            http_response_code(404);
            exit;
        }
        $path = ltrim($path, '/');
        media_serve_send($path);
    }
}
