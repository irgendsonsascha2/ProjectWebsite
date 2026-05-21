<?php

require_once __DIR__ . '/app_env.php';
require_once __DIR__ . '/security_headers.php';
security_headers_send();

// Lazy sessions: Only set a session cookie when needed.
// - If a session cookie already exists, always resume.
// - For login/register/account flows and POST requests, start a session.
// - Admin pages always require a session.
$isPost = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$shouldStartSession = false;
if (session_status() === PHP_SESSION_NONE) {
    configure_session_cookie_params();
    $sessionCookieName = session_name();
    $hasSessionCookie = isset($_COOKIE[$sessionCookieName]) && (string) $_COOKIE[$sessionCookieName] !== '';
    $page = (string)($_GET['page'] ?? '');
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $isAdminScript = strpos($script, '/pages/admin/') !== false;
    $needsSessionForPage = in_array($page, ['login', 'register', 'account', 'two_factor', 'create_project', 'edit_project'], true);
    $shouldStartSession = $hasSessionCookie || $isPost || $needsSessionForPage || $isAdminScript;
    if ($shouldStartSession) {
        session_start();
    }
}

require_once __DIR__ . '/csrf.php';

if (app_debug_enabled()) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    ini_set('log_errors', '1');
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    ini_set('error_log', $logDir . '/php_errors.log');
    error_reporting(E_ALL);
    register_shutdown_function(function () {
        $error = error_get_last();
        if ($error !== null) {
            error_log('FATAL: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
        }
    });
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/user_db.php';
require_once __DIR__ . '/authz.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/site_settings.php';
require_once __DIR__ . '/svg_icons.php';

$sessionActive = session_status() === PHP_SESSION_ACTIVE;
$effectiveRole = $sessionActive ? ($_SESSION['role'] ?? 'viewer') : 'viewer';
$effectivePermissions = [];

if (!isset($db)) {
    [$client, $db] = get_request_mongo_connection($effectiveRole);
}

site_settings_apply($db);

if ($sessionActive && isset($_SESSION['user_id'])) {
    authz_sync_session_from_db();
    if (! isset($_SESSION['user_id'])) {
        $sessionActive = false;
    } else {
        $effectiveRole = (string) ($_SESSION['role'] ?? 'viewer');
        $effectivePermissions = (array) ($_SESSION['permissions'] ?? []);
        [$client, $db] = get_request_mongo_connection($effectiveRole);
    }
}

if (! $sessionActive || ! isset($_SESSION['user_id'])) {
    if ($sessionActive) {
        if (!isset($_SESSION['role'])) {
            $_SESSION['role'] = 'viewer';
        }
        if (!isset($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
            $roleData = $db->roles_config->findOne(['role' => $_SESSION['role']]);
            if ($roleData && isset($roleData['permissions'])) {
                $perms = $roleData['permissions'];
                $_SESSION['permissions'] = is_array($perms) ? $perms : iterator_to_array($perms);
            } else {
                $_SESSION['permissions'] = [];
            }
        }
        $effectiveRole = (string)($_SESSION['role'] ?? 'viewer');
        $effectivePermissions = (array)($_SESSION['permissions'] ?? []);
        [$client, $db] = get_request_mongo_connection($effectiveRole);
    } else {
        // No session: act as viewer, but still load permissions for can()
        $roleData = $db->roles_config->findOne(['role' => 'viewer']);
        if ($roleData && isset($roleData['permissions'])) {
            $perms = $roleData['permissions'];
            $effectivePermissions = is_array($perms) ? $perms : iterator_to_array($perms);
        } else {
            $effectivePermissions = [];
        }
        $effectiveRole = 'viewer';
        [$client, $db] = get_request_mongo_connection($effectiveRole);
    }
}

if (!function_exists('can')) {
    function can($permission) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return isset($_SESSION['permissions']) && in_array($permission, $_SESSION['permissions'], true);
        }
        $perms = $GLOBALS['effective_permissions'] ?? [];
        return is_array($perms) && in_array($permission, $perms, true);
    }
}

$GLOBALS['effective_role'] = $effectiveRole;
$GLOBALS['effective_permissions'] = $effectivePermissions;

if (!function_exists('can_edit_project')) {
    function can_edit_project($project) {
        return authz_can_edit_project($project);
    }
}

if (!function_exists('sanitize_extension')) {
    function sanitize_extension($ext) {
        $safe = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
        return $safe ? '.' . $safe : '';
    }
}

if (!function_exists('detect_media_type')) {
    function detect_media_type($tmpPath, $originalName = null) {
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($tmpPath);
            if ($mime) {
                if (strpos($mime, 'image/') === 0) {
                    return 'image';
                }
                if (strpos($mime, 'video/') === 0) {
                    return 'video';
                }
            }
        }

        if (function_exists('getimagesize')) {
            $imgInfo = @getimagesize($tmpPath);
            if ($imgInfo !== false) {
                return 'image';
            }
        }

        if ($originalName) {
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp'];
            $videoExts = ['mp4', 'webm', 'ogg', 'ogv', 'mov', 'avi', 'mkv'];
            if (in_array($ext, $imageExts, true)) {
                return 'image';
            }
            if (in_array($ext, $videoExts, true)) {
                return 'video';
            }
        }

        return null;
    }
}

if (!function_exists('media_upload_limit_label')) {
    function media_upload_limit_label(int $bytes): string {
        if ($bytes >= 1024 * 1024 * 1024) {
            $gb = $bytes / (1024 * 1024 * 1024);
            return ((int) $gb === $gb) ? ((int) $gb . ' GB') : (round($gb, 1) . ' GB');
        }
        return (int) ($bytes / 1024 / 1024) . ' MB';
    }
}

if (!function_exists('media_upload_ini_to_bytes')) {
    function media_upload_ini_to_bytes(string $value): int {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        if ($unit === 'g') {
            return (int) ($number * 1024 * 1024 * 1024);
        }
        if ($unit === 'm') {
            return (int) ($number * 1024 * 1024);
        }
        if ($unit === 'k') {
            return (int) ($number * 1024);
        }
        return (int) $number;
    }
}

if (!function_exists('request_is_ajax')) {
    function request_is_ajax(): bool {
        return (isset($_POST['ajax']) && $_POST['ajax'] === '1')
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && in_array(
                strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']),
                ['xmlhttprequest', 'fetch'],
                true
            ));
    }
}

if ($isPost) {
    csrf_verify_or_exit();
}

if (!function_exists('media_upload_request_body_too_large')) {
    /**
     * Wenn post_max_size überschritten wird, verwirft PHP $_POST und $_FILES — der Upload „verschwindet“ still.
     */
    function media_upload_request_body_too_large(): ?string {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return null;
        }
        $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($length <= 0) {
            return null;
        }
        $postMax = media_upload_ini_to_bytes((string) ini_get('post_max_size'));
        if ($postMax > 0 && $length > $postMax) {
            return 'Die Anfrage ist für PHP zu groß (post_max_size '
                . ini_get('post_max_size')
                . ', gesendet ca. '
                . media_upload_limit_label($length)
                . '). Server mit „make php“ oder ./serve-php.sh starten.';
        }
        return null;
    }
}

if (!function_exists('validate_image_resolution')) {
    /**
     * UHD 4K: längere Seite max. 3840 px, kürzere max. 2160 px (Hoch- und Querformat).
     * Keine Prüfung, wenn die Auflösung nicht ermittelbar ist (z. B. manche SVG).
     */
    function validate_image_resolution($tmpPath) {
        if (!function_exists('getimagesize')) {
            return null;
        }
        $info = @getimagesize($tmpPath);
        if ($info === false || !isset($info[0], $info[1])) {
            return null;
        }
        $w = (int) $info[0];
        $h = (int) $info[1];
        if ($w < 1 || $h < 1) {
            return null;
        }
        $maxW = MEDIA_UPLOAD_MAX_IMAGE_WIDTH;
        $maxH = MEDIA_UPLOAD_MAX_IMAGE_HEIGHT;
        if (max($w, $h) > $maxW || min($w, $h) > $maxH) {
            return "Bildauflösung zu hoch (max. 4K / {$maxW}×{$maxH} px, aktuell {$w}×{$h} px).";
        }
        return null;
    }
}

if (!function_exists('validate_media_upload')) {
    function validate_media_upload($tmpPath, $sizeBytes, $type) {
        $limits = [
            'image' => MEDIA_UPLOAD_MAX_IMAGE_BYTES,
            'video' => MEDIA_UPLOAD_MAX_VIDEO_BYTES,
        ];
        if (!isset($limits[$type])) {
            return "Ungültiger Medientyp.";
        }
        if ($sizeBytes > $limits[$type]) {
            return "Datei zu groß (max. " . media_upload_limit_label($limits[$type]) . ").";
        }
        if ($type === 'image') {
            $resolutionError = validate_image_resolution($tmpPath);
            if ($resolutionError !== null) {
                return $resolutionError;
            }
        }
        if (!is_uploaded_file($tmpPath)) {
            return "Ungültiger Upload.";
        }
        return null;
    }
}

if (!function_exists('upload_error_message')) {
    function upload_error_message($error) {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
                return "Datei zu groß (Server-Limit).";
            case UPLOAD_ERR_FORM_SIZE:
                return "Datei zu groß (Form-Limit).";
            case UPLOAD_ERR_PARTIAL:
                return "Datei nur teilweise hochgeladen.";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Temporäres Verzeichnis fehlt.";
            case UPLOAD_ERR_CANT_WRITE:
                return "Datei konnte nicht geschrieben werden.";
            case UPLOAD_ERR_EXTENSION:
                return "Upload durch Server-Erweiterung gestoppt.";
            default:
                return "Unbekannter Upload-Fehler.";
        }
    }
}

if (!function_exists('normalize_gallery')) {
    function normalize_gallery($gallery) {
        if (is_array($gallery)) {
            return $gallery;
        }
        if ($gallery instanceof Traversable) {
            return iterator_to_array($gallery);
        }
        return [];
    }
}
