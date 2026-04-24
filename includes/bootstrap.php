<?php
// Lazy sessions: Only set a session cookie when needed.
// - If a session cookie already exists, always resume.
// - For login/register/account flows and POST requests, start a session.
// - Admin pages always require a session.
$shouldStartSession = false;
if (session_status() === PHP_SESSION_NONE) {
    $sessionCookieName = session_name();
    $hasSessionCookie = isset($_COOKIE[$sessionCookieName]) && (string)$_COOKIE[$sessionCookieName] !== '';
    $isPost = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    $page = (string)($_GET['page'] ?? '');
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $isAdminScript = strpos($script, '/pages/admin/') !== false;
    $needsSessionForPage = in_array($page, ['login', 'register', 'account'], true);
    $shouldStartSession = $hasSessionCookie || $isPost || $needsSessionForPage || $isAdminScript;
    if ($shouldStartSession) {
        session_start();
    }
}

if (isset($_GET['debug']) && $_GET['debug'] === '1') {
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
require_once __DIR__ . '/svg_icons.php';

$sessionActive = session_status() === PHP_SESSION_ACTIVE;
$effectiveRole = $sessionActive ? ($_SESSION['role'] ?? 'viewer') : 'viewer';
$effectivePermissions = [];

if (!isset($db)) {
    [$client, $db] = get_request_mongo_connection($effectiveRole);
}

if ($sessionActive && isset($_SESSION['user_id']) && (
    !isset($_SESSION['permissions']) ||
    !is_array($_SESSION['permissions']) ||
    !isset($_SESSION['role']) ||
    !isset($_SESSION['email'])
)) {
    try {
        $userId = new \MongoDB\BSON\ObjectId($_SESSION['user_id']);
        // Nutzer anhand der ID laden: nicht die rollenbeschränkte $db-Connection nutzen —
        // sonst schlägt findOne fehl, Session wird geleert, wirken wie „abgemeldet“ (z. B. Admin-URL).
        [, $dbForUserRead] = get_admin_mongo_connection();
        $user = $dbForUserRead->users->findOne(['_id' => $userId]);
        if ($user) {
            $_SESSION['email'] = $user['email'] ?? '';
            $_SESSION['username'] = $user['username'] ?? '';
            $_SESSION['role'] = $user['role'] ?? '';
            [$client, $db] = get_request_mongo_connection($_SESSION['role']);
            if ($_SESSION['role']) {
                $roleData = $db->roles_config->findOne(['role' => $_SESSION['role']]);
                if ($roleData && isset($roleData['permissions'])) {
                    $_SESSION['permissions'] = iterator_to_array($roleData['permissions']);
                }
            }
        } else {
            $_SESSION = [];
        }
    } catch (Exception $e) {
        $_SESSION = [];
    }
}

if (!$sessionActive || !isset($_SESSION['user_id'])) {
    if ($sessionActive) {
        if (!isset($_SESSION['role'])) {
            $_SESSION['role'] = 'viewer';
        }
        if (!isset($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
            $roleData = $db->roles_config->findOne(['role' => $_SESSION['role']]);
            if ($roleData && isset($roleData['permissions'])) {
                $_SESSION['permissions'] = iterator_to_array($roleData['permissions']);
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
            $effectivePermissions = iterator_to_array($roleData['permissions']);
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
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        if (can('edit_all')) {
            return true;
        }
        if (can('edit_own') && isset($project['author_id'])) {
            return (string)$project['author_id'] === $_SESSION['user_id'];
        }
        return false;
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
            $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'svg'];
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

if (!function_exists('validate_media_upload')) {
    function validate_media_upload($tmpPath, $sizeBytes, $type) {
        $limits = [
            'image' => 10 * 1024 * 1024,
            'video' => 50 * 1024 * 1024
        ];
        if (!isset($limits[$type])) {
            return "Ungültiger Medientyp.";
        }
        if ($sizeBytes > $limits[$type]) {
            return "Datei zu groß (max. " . ($limits[$type] / 1024 / 1024) . "MB).";
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

if (!defined('MEDIA_UPLOAD_MAX_FILES')) {
    define('MEDIA_UPLOAD_MAX_FILES', 50);
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
