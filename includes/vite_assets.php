<?php

/**
 * Minimaler Vite-Loader (React-Frontend), ohne Framework-Abhängigkeiten.
 *
 * Dev:
 *   - startet Vite in /frontend (Default-Port 5173)
 *   - setzt VITE_DEV_SERVER_URL (z.B. http://127.0.0.1:5173)
 *
 * Prod:
 *   - lädt Manifest aus /react-dist/.vite/manifest.json
 */
function vite_react_assets(string $entry = 'src/main.tsx'): void
{
    $devServer = getenv('VITE_DEV_SERVER_URL');
    if ($devServer) {
        $devServer = rtrim($devServer, '/');
        echo '<script type="module" src="' . htmlspecialchars($devServer . '/@vite/client', ENT_QUOTES, 'UTF-8') . '"></script>' . PHP_EOL;
        echo '<script type="module" src="' . htmlspecialchars($devServer . '/' . $entry, ENT_QUOTES, 'UTF-8') . '"></script>' . PHP_EOL;
        return;
    }

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
    // Wichtig: absolute Pfade, damit Subpages wie /pages/admin/* auch funktionieren.
    $baseUrl = '/react-dist/';

    if (isset($chunk['css']) && is_array($chunk['css'])) {
        foreach ($chunk['css'] as $cssFile) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl . ltrim($cssFile, '/'), ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
        }
    }

    if (isset($chunk['file'])) {
        echo '<script type="module" src="' . htmlspecialchars($baseUrl . ltrim((string)$chunk['file'], '/'), ENT_QUOTES, 'UTF-8') . '"></script>' . PHP_EOL;
    }
}

