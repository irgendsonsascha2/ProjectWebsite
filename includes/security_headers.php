<?php

if (! function_exists('security_headers_send')) {
    /**
     * Setzt konservative Security-Header (nur wenn noch keine Ausgabe).
     */
    function security_headers_send(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Externe Skripte unter js/; Inline nur noch für Vite-HMR (Dev).
        $scriptSrc = "'self'";
        $connectSrc = "'self'";
        if (getenv('VITE_HMR') === '1' || getenv('VITE_HMR') === 'true') {
            $devUrl = trim((string) (getenv('VITE_DEV_SERVER_URL') ?: 'http://127.0.0.1:5173'));
            $devHost = parse_url($devUrl, PHP_URL_HOST);
            $devPort = parse_url($devUrl, PHP_URL_PORT);
            $devScheme = parse_url($devUrl, PHP_URL_SCHEME) ?: 'http';
            if (is_string($devHost) && $devHost !== '') {
                $origin = $devScheme.'://'.$devHost.($devPort ? ':'.$devPort : '');
                $scriptSrc .= ' '.$origin;
                $connectSrc .= ' '.$origin;
                if ($devScheme === 'http') {
                    $connectSrc .= ' ws://'.$devHost.($devPort ? ':'.$devPort : '');
                } elseif ($devScheme === 'https') {
                    $connectSrc .= ' wss://'.$devHost.($devPort ? ':'.$devPort : '');
                }
            }
        }

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src {$scriptSrc}",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "media-src 'self' blob:",
            "font-src 'self' data:",
            "connect-src {$connectSrc}",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        header('Content-Security-Policy: '.$csp);
    }
}
