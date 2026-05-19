<?php

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/vite_assets.php';

/**
 * Admin master layout:
 * - ensures admin session + role
 * - applies theme bootstrap (data-theme)
 * - loads Vite assets
 * - renders a consistent admin navigation frame
 */

if (!function_exists('admin_require_access')) {
    /**
     * @param string[] $allowedRoles
     */
    function admin_require_access(array $allowedRoles = ['admin']): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ../../index.php?page=login');
            exit();
        }
        $role = (string)($_SESSION['role'] ?? '');
        $allowedRoles = array_values(array_filter(array_map(function ($r) {
            return is_string($r) ? trim($r) : '';
        }, $allowedRoles), function ($r) {
            return $r !== '';
        }));
        if (count($allowedRoles) === 0) {
            $allowedRoles = ['admin'];
        }

        if (!in_array($role, $allowedRoles, true)) {
            die("<h1>Zugriff verweigert</h1><p>Diese Seite ist nicht für Ihre Rolle freigeschaltet.</p>");
        }
    }
}

if (!function_exists('admin_theme_bootstrap_script')) {
    function admin_theme_bootstrap_script(): string
    {
        return <<<HTML
<script>
    (function () {
        try {
            var KEY = 'portfolio-theme';
            var t = localStorage.getItem(KEY);
            if (t !== 'dark' && t !== 'light') {
                t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-theme', t);
        } catch (e) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })();
</script>
HTML;
    }
}

if (!function_exists('admin_nav_html')) {
    function admin_nav_html(string $active = ''): string
    {
        $role = (string)($_SESSION['role'] ?? '');
        $isAdmin = $role === 'admin';
        $items = [];

        // Always left-most
        $items['home'] = ['href' => '../../index.php', 'label' => 'Zur Hauptseite'];

        // Dashboard only makes sense for admins
        if ($isAdmin) {
            $items['dashboard'] = ['href' => 'index.php', 'label' => 'Dashboard'];
        }

        // Content
        $items['home_profile'] = ['href' => 'home_profile.php', 'label' => 'Startseite'];
        $items['legal_impressum'] = ['href' => 'legal_page_edit.php?key=impressum', 'label' => 'Impressum'];
        $items['legal_datenschutz'] = ['href' => 'legal_page_edit.php?key=datenschutz', 'label' => 'Datenschutz'];
        $items['legal_nutzungsbedingungen'] = [
            'href' => 'legal_page_edit.php?key=nutzungsbedingungen',
            'label' => 'Nutzungsbedingungen',
        ];

        if ($isAdmin && function_exists('can') && can('generate_codes')) {
            $items['invite_codes'] = ['href' => 'invite_codes.php', 'label' => 'Einladungscodes'];
        }

        if ($isAdmin && is_file(__DIR__ . '/registration_requests.php')) {
            $items['registration_requests'] = ['href' => 'registration_requests.php', 'label' => 'Registrierungsanfragen'];
        }

        // Access management + maintenance (admin only)
        if ($isAdmin) {
            $items['settings'] = ['href' => 'settings.php', 'label' => 'Einstellungen'];
            $items['roles'] = ['href' => 'roles.php', 'label' => 'Rollen'];
            $items['permissions'] = ['href' => 'permissions.php', 'label' => 'Berechtigungen'];
            $items['db_scripts'] = ['href' => 'db_scripts.php', 'label' => 'DB-Skripte'];
        }

        $out = '<div class="admin-nav">';
        foreach ($items as $key => $item) {
            $isActive = ($key === $active);
            $class = $isActive ? ' class="is-active"' : '';
            $out .= '<a href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '"' . $class . '>'
                . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8')
                . '</a>';
        }
        $out .= '</div>';

        return $out;
    }
}

if (!function_exists('admin_render_page')) {
    /**
     * @param callable():void $renderContent
     */
    function admin_render_page(string $title, string $activeNav, callable $renderContent, array $allowedRoles = ['admin']): void
    {
        admin_require_access($allowedRoles);
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        echo "<!DOCTYPE html>\n<html lang=\"de\">\n<head>\n";
        echo "    <meta charset=\"UTF-8\">\n";
        echo "    <title>{$safeTitle}</title>\n";
        echo admin_theme_bootstrap_script() . "\n";
        vite_react_assets('src/main.tsx');
        echo "</head>\n<body class=\"admin-page\">\n";
        echo "    <div class=\"container\">\n";
        echo admin_nav_html($activeNav) . "\n";
        echo "        <hr>\n";
        $renderContent();
        echo "    </div>\n";
        echo "</body>\n</html>\n";
    }
}

