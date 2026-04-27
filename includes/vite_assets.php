<?php

/**
 * Minimaler Vite-Loader (React-Frontend), ohne Framework-Abhängigkeiten.
 *
 * **Standard (empfohlen):** Es werden die gebauten Dateien aus `react-dist/.vite/manifest.json`
 * geliefert (nach `cd frontend && npm run build`) — ein PHP-Server genügt, kein laufendes Vite nötig.
 *
 * **HMR / Vite-Dev (optional):** In `.env.local` setzen:
 *   - `VITE_HMR=1` (oder `true`)
 *   - `VITE_DEV_SERVER_URL=http://127.0.0.1:5173` (Port wie `npm run dev`)
 * Dann binden wir `@vite/client` + Entry vom Dev-Server. Läuft Vite nicht, fällt die Logik auf
 * `react-dist` zurück, sofern ein Build existiert.
 *
 * **Erzwingen nur gebaut:** `VITE_USE_BUILT_ASSETS=1` — niemals Dev-Skripte (z. B. in CI).
 *
 * @see README.md *Wenn kein Styling / keine React-Assets (Fehlersuche)*
 */
if (!function_exists('vite_wants_built_only')) {
    function vite_wants_built_only(): bool
    {
        $v = strtolower((string) getenv('VITE_USE_BUILT_ASSETS'));
        return $v === '1' || $v === 'true' || $v === 'yes';
    }
}

if (!function_exists('vite_wants_hmr')) {
    function vite_wants_hmr(): bool
    {
        $v = strtolower((string) getenv('VITE_HMR'));
        return $v === '1' || $v === 'true' || $v === 'yes';
    }
}

if (!function_exists('vite_dev_base_url_resolved')) {
    /**
     * Vite-Dev-URL: Host/Port/Scheme exakt aus VITE_DEV_SERVER_URL (Vite lauscht i. d. R. nur per HTTP).
     * Weder Host an die PHP-Seite anpassen (localhost/IPv4-Problem) noch Scheme an HTTPS der Seite:
     * sonst würde z. B. https://127.0.0.1:5173 verlangt, obwohl Vite nur http spricht, oder
     * bei https-Seite + http-Vite gäbe es Mixed-Content-Block (User muss Vite per https/Proxy
     * betreiben oder auf gebautes manifest-only ausweichen / VITE_HMR ausschalten).
     */
    function vite_dev_base_url_resolved(string $devServer): string
    {
        $p = parse_url(rtrim($devServer, '/'));
        if (!is_array($p) || !isset($p['host'], $p['scheme'])) {
            return rtrim($devServer, '/');
        }
        if (PHP_SAPI === 'cli' && !isset($_SERVER['HTTP_HOST'])) {
            return rtrim($devServer, '/');
        }
        $port = (int) ($p['port'] ?? 5173);
        $scheme = ($p['scheme'] === 'https') ? 'https' : 'http';
        $host = (string) $p['host'];
        return $scheme . '://' . $host . ':' . $port;
    }
}

if (!function_exists('vite_dev_server_reachable')) {
    function vite_dev_server_reachable(string $baseUrl): bool
    {
        $p = parse_url(rtrim($baseUrl, '/'));
        if (!is_array($p) || !isset($p['host'])) {
            return false;
        }
        $scheme = $p['scheme'] ?? 'http';
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }
        $host = $p['host'];
        $port = isset($p['port']) ? (int) $p['port'] : ($scheme === 'https' ? 443 : 80);
        $remote = $scheme === 'https' ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client($remote, $errno, $errstr, 0.2, STREAM_CLIENT_CONNECT);
        if ($fp !== false) {
            fclose($fp);
            return true;
        }
        return false;
    }
}

if (!function_exists('vite_dev_server_serves_hmr_client')) {
    function vite_dev_server_serves_hmr_client(string $baseUrl): bool
    {
        $url = rtrim($baseUrl, '/') . '/react-dist/@vite/client';
        $ctx = stream_context_create(['http' => ['timeout' => 0.5, 'ignore_errors' => true]]);
        $h = @get_headers($url, true, $ctx);
        if (!is_array($h) || !isset($h[0]) || !is_string($h[0])) {
            return false;
        }
        return str_contains($h[0], '200');
    }
}

if (!function_exists('vite_emit_css_links_from_manifest')) {
    /**
     * Nur `link rel=stylesheet` aus dem Build-Manifest (gleicher Eintrag wie bei HMR).
     * Im HMR-Modus zusätzlich zu den Vite-Skripten: Fallback, wenn Module nicht laden
     * (Vite aus, Mixed-Content, Netzwerk), damit die Seite nicht „nackt“ bleibt.
     */
    function vite_emit_css_links_from_manifest(string $entry = 'src/main.tsx'): void
    {
        $manifestPath = __DIR__ . '/../react-dist/.vite/manifest.json';
        if (!is_file($manifestPath)) {
            return;
        }

        $raw = file_get_contents($manifestPath);
        if ($raw === false) {
            return;
        }

        $manifest = json_decode($raw, true);
        if (!is_array($manifest) || !isset($manifest[$entry])) {
            return;
        }

        $chunk = $manifest[$entry];
        $baseUrl = '/react-dist/';

        if (isset($chunk['css']) && is_array($chunk['css'])) {
            foreach ($chunk['css'] as $cssFile) {
                echo '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl . ltrim($cssFile, '/'), ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
            }
        }
    }
}

if (!function_exists('vite_react_assets_from_manifest')) {
    function vite_react_assets_from_manifest(string $entry = 'src/main.tsx'): void
    {
        vite_emit_css_links_from_manifest($entry);

        $manifestPath = __DIR__ . '/../react-dist/.vite/manifest.json';
        if (!is_file($manifestPath)) {
            return;
        }

        $raw = file_get_contents($manifestPath);
        if ($raw === false) {
            return;
        }

        $manifest = json_decode($raw, true);
        if (!is_array($manifest) || !isset($manifest[$entry])) {
            return;
        }

        $chunk = $manifest[$entry];
        $baseUrl = '/react-dist/';

        if (isset($chunk['file'])) {
            echo '<script type="module" src="' . htmlspecialchars($baseUrl . ltrim((string) $chunk['file'], '/'), ENT_QUOTES, 'UTF-8') . '"></script>' . PHP_EOL;
        }
    }
}

function vite_react_assets(string $entry = 'src/main.tsx'): void
{
    if (vite_wants_built_only()) {
        vite_react_assets_from_manifest($entry);
        return;
    }

    $devServer = getenv('VITE_DEV_SERVER_URL');
    $isString = is_string($devServer) && $devServer !== '';
    if ($isString && vite_wants_hmr()) {
        $devServer = rtrim($devServer, '/');
        $devServer = vite_dev_base_url_resolved($devServer);
        if (vite_dev_server_reachable($devServer) && vite_dev_server_serves_hmr_client($devServer)) {
            vite_emit_css_links_from_manifest($entry);
            $basePath = '/react-dist';
            // Wenn wir nicht über Vites index.html gehen (hier: PHP-Layout), fehlt das von
            // @vitejs/plugin-react injizierte "preamble" für React Fast Refresh.
            // Ohne dieses Preamble wirft Vite beim Import von TSX-Modulen:
            // "@vitejs/plugin-react can't detect preamble".
            echo '<script type="module">' . PHP_EOL;
            echo '  import RefreshRuntime from ' . json_encode($devServer . $basePath . '/@react-refresh') . ';' . PHP_EOL;
            echo '  RefreshRuntime.injectIntoGlobalHook(window);' . PHP_EOL;
            echo '  window.$RefreshReg$ = () => {};' . PHP_EOL;
            echo '  window.$RefreshSig$ = () => (type) => type;' . PHP_EOL;
            echo '  window.__vite_plugin_react_preamble_installed__ = true;' . PHP_EOL;
            echo '</script>' . PHP_EOL;
            echo '<script type="module" src="' . htmlspecialchars($devServer . $basePath . '/@vite/client', ENT_QUOTES, 'UTF-8') . '"></script>' . PHP_EOL;
            echo '<script type="module" src="' . htmlspecialchars($devServer . $basePath . '/' . ltrim($entry, '/'), ENT_QUOTES, 'UTF-8') . '"></script>' . PHP_EOL;
            return;
        }
    }

    vite_react_assets_from_manifest($entry);
}
