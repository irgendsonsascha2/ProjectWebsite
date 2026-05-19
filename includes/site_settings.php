<?php

use MongoDB\BSON\UTCDateTime;

if (!function_exists('site_settings_defaults')) {
    /**
     * @return array<string, int>
     */
    function site_settings_defaults(): array
    {
        return [
            'max_image_mb' => 50,
            'max_video_mb' => 2048,
            'max_image_width' => 3840,
            'max_image_height' => 2160,
            'max_files_per_upload' => 50,
            'project_detail_media_limit' => 30,
            'comment_text_max_length' => 400,
        ];
    }
}

if (!function_exists('site_settings_document_id')) {
    function site_settings_document_id(): string
    {
        return 'site_settings';
    }
}

if (!function_exists('site_settings_normalize')) {
    /**
     * @param array<string, mixed>|null $doc
     * @return array<string, int>
     */
    function site_settings_normalize(?array $doc): array
    {
        $defaults = site_settings_defaults();
        $readInt = static function (string $key, int $min, int $max) use ($doc, $defaults): int {
            $raw = $doc[$key] ?? $defaults[$key];
            if (is_numeric($raw)) {
                $value = (int) $raw;
            } else {
                $value = (int) $defaults[$key];
            }
            return max($min, min($max, $value));
        };

        return [
            'max_image_mb' => $readInt('max_image_mb', 1, 500),
            'max_video_mb' => $readInt('max_video_mb', 1, 10240),
            'max_image_width' => $readInt('max_image_width', 320, 8192),
            'max_image_height' => $readInt('max_image_height', 240, 8192),
            'max_files_per_upload' => $readInt('max_files_per_upload', 1, 100),
            'project_detail_media_limit' => $readInt('project_detail_media_limit', 1, 200),
            'comment_text_max_length' => $readInt('comment_text_max_length', 50, 2000),
        ];
    }
}

if (!function_exists('site_settings_load')) {
    /**
     * @return array<string, int>
     */
    function site_settings_load($db = null, bool $forceReload = false): array
    {
        if (!$forceReload && isset($GLOBALS['site_settings_cache']) && is_array($GLOBALS['site_settings_cache'])) {
            return $GLOBALS['site_settings_cache'];
        }

        $doc = null;
        try {
            if ($db === null && isset($GLOBALS['db'])) {
                $db = $GLOBALS['db'];
            }
            if ($db !== null) {
                $found = $db->site_pages->findOne(['_id' => site_settings_document_id()]);
                if ($found) {
                    $doc = is_array($found) ? $found : iterator_to_array($found);
                }
            }
        } catch (Exception $e) {
            $doc = null;
        }

        $GLOBALS['site_settings_cache'] = site_settings_normalize($doc);
        return $GLOBALS['site_settings_cache'];
    }
}

if (!function_exists('site_settings_clear_cache')) {
    function site_settings_clear_cache(): void
    {
        unset($GLOBALS['site_settings_cache']);
    }
}

if (!function_exists('site_settings_apply')) {
    function site_settings_apply($db = null): void
    {
        $settings = site_settings_load($db);

        if (!defined('MEDIA_UPLOAD_MAX_IMAGE_BYTES')) {
            define('MEDIA_UPLOAD_MAX_IMAGE_BYTES', $settings['max_image_mb'] * 1024 * 1024);
        }
        if (!defined('MEDIA_UPLOAD_MAX_IMAGE_WIDTH')) {
            define('MEDIA_UPLOAD_MAX_IMAGE_WIDTH', $settings['max_image_width']);
        }
        if (!defined('MEDIA_UPLOAD_MAX_IMAGE_HEIGHT')) {
            define('MEDIA_UPLOAD_MAX_IMAGE_HEIGHT', $settings['max_image_height']);
        }
        if (!defined('MEDIA_UPLOAD_MAX_VIDEO_BYTES')) {
            define('MEDIA_UPLOAD_MAX_VIDEO_BYTES', $settings['max_video_mb'] * 1024 * 1024);
        }
        if (!defined('MEDIA_UPLOAD_MAX_FILES')) {
            define('MEDIA_UPLOAD_MAX_FILES', $settings['max_files_per_upload']);
        }
        if (!defined('PROJECT_DETAIL_MEDIA_LIMIT')) {
            define('PROJECT_DETAIL_MEDIA_LIMIT', $settings['project_detail_media_limit']);
        }
        if (!defined('COMMENT_TEXT_MAX_LENGTH')) {
            define('COMMENT_TEXT_MAX_LENGTH', $settings['comment_text_max_length']);
        }
    }
}

if (!function_exists('site_settings_build_document')) {
    /**
     * @param array<string, int> $settings
     * @return array<string, mixed>
     */
    function site_settings_build_document(array $settings, UTCDateTime $now): array
    {
        $normalized = site_settings_normalize($settings);

        return [
            '_id' => site_settings_document_id(),
            'page_kind' => 'settings',
            'max_image_mb' => $normalized['max_image_mb'],
            'max_video_mb' => $normalized['max_video_mb'],
            'max_image_width' => $normalized['max_image_width'],
            'max_image_height' => $normalized['max_image_height'],
            'max_files_per_upload' => $normalized['max_files_per_upload'],
            'project_detail_media_limit' => $normalized['project_detail_media_limit'],
            'comment_text_max_length' => $normalized['comment_text_max_length'],
            'created_at' => $now,
            'updated_at' => $now,
            'updated_by' => '',
        ];
    }
}

if (!function_exists('site_settings_seed')) {
    /**
     * @return 'inserted'|'replaced'
     */
    function site_settings_seed($db, bool $preserveCreatedAt = true): string
    {
        if (!function_exists('site_page_ensure_collection')) {
            require_once __DIR__ . '/site_pages.php';
        }
        site_page_ensure_collection($db);

        $existing = $db->site_pages->findOne(['_id' => site_settings_document_id()]);
        $now = new UTCDateTime();
        $doc = site_settings_build_document(site_settings_defaults(), $now);
        if ($existing && $preserveCreatedAt && !empty($existing['created_at'])) {
            $doc['created_at'] = $existing['created_at'];
        }

        $db->site_pages->replaceOne(['_id' => site_settings_document_id()], $doc, ['upsert' => true]);

        return $existing ? 'replaced' : 'inserted';
    }
}
