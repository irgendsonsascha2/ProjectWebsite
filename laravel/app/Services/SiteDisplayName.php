<?php

namespace App\Services;

use App\Support\MailDisplayName;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Öffentlicher Website-Name aus site_pages.site_settings (MongoDB).
 */
class SiteDisplayName
{
    private const DOC_ID = 'site_settings';

    private const DEFAULT = 'Portfolio';

    private const MAX_LEN = 80;

    public function resolve(): string
    {
        try {
            $database = DB::connection('mongodb')->getMongoDB();
            $doc = $database->selectCollection('site_pages')->findOne(['_id' => self::DOC_ID]);
            if ($doc === null) {
                return $this->fallback();
            }
            $array = is_array($doc) ? $doc : (method_exists($doc, 'getArrayCopy') ? $doc->getArrayCopy() : (array) $doc);
            $raw = $array['site_name'] ?? null;
            if (! is_string($raw) || trim($raw) === '') {
                return $this->fallback();
            }

            return $this->normalize($raw);
        } catch (Throwable) {
            return $this->fallback();
        }
    }

    private function fallback(): string
    {
        $env = env('APP_NAME', self::DEFAULT);
        if (! is_string($env) || trim($env) === '') {
            return self::DEFAULT;
        }

        return $this->normalize($env);
    }

    private function normalize(string $name): string
    {
        $trimmed = MailDisplayName::sanitize($name);
        if ($trimmed === '') {
            return self::DEFAULT;
        }

        return mb_substr($trimmed, 0, self::MAX_LEN);
    }
}
