<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!class_exists('MongoDB\Client')) {
    require __DIR__ . '/../vendor/autoload.php';
}

if (!isset($db)) {
    $client = new \MongoDB\Client("mongodb://localhost:27017");
    $db = $client->portfolio_db;
}

if (isset($_SESSION['user_id']) && (
    !isset($_SESSION['permissions']) ||
    !is_array($_SESSION['permissions']) ||
    !isset($_SESSION['role']) ||
    !isset($_SESSION['email'])
)) {
    try {
        $userId = new \MongoDB\BSON\ObjectId($_SESSION['user_id']);
        $user = $db->users->findOne(['_id' => $userId]);
        if ($user) {
            $_SESSION['email'] = $user['email'] ?? '';
            $_SESSION['username'] = $user['username'] ?? '';
            $_SESSION['role'] = $user['role'] ?? '';
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

if (!function_exists('can')) {
    function can($permission) {
        return isset($_SESSION['permissions']) && in_array($permission, $_SESSION['permissions']);
    }
}

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
    function detect_media_type($tmpPath) {
        $mime = mime_content_type($tmpPath);
        if (strpos($mime, 'image/') === 0) {
            return 'image';
        }
        if (strpos($mime, 'video/') === 0) {
            return 'video';
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
