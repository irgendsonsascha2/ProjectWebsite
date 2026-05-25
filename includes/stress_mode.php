<?php

/**
 * Schutzmodus bei hoher Last: Gäste ohne Login von projektbezogenen Inhalten und Medien ausschließen.
 *
 * Aktivierung:
 * - Admin: stress_mode_enabled (manuell)
 * - Env: STRESS_MODE=1
 * - Automatisch: stress_auto_* in Site-Einstellungen (Request-Rate über logs/)
 */

if (! function_exists('stress_mode_env_forced')) {
    function stress_mode_env_forced(): bool
    {
        $v = getenv('STRESS_MODE');
        if ($v === false) {
            return false;
        }

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes'], true);
    }
}

if (! function_exists('stress_mode_env_bool')) {
    function stress_mode_env_bool(string $name, bool $default): bool
    {
        $v = getenv($name);
        if ($v === false) {
            return $default;
        }

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes'], true);
    }
}

if (! function_exists('stress_mode_env_int')) {
    function stress_mode_env_int(string $name, int $default, int $min, int $max): int
    {
        $v = getenv($name);
        if ($v === false || ! is_numeric($v)) {
            return $default;
        }

        return max($min, min($max, (int) $v));
    }
}

if (! function_exists('stress_mode_auto_config')) {
    /**
     * @return array{enabled: bool, activate_rpm: int, release_rpm: int, hold_seconds: int, window_seconds: int}
     */
    function stress_mode_auto_config(): array
    {
        $defaults = [
            'enabled' => true,
            'activate_rpm' => 600,
            'release_rpm' => 250,
            'hold_seconds' => 600,
            'window_seconds' => 60,
        ];

        if (function_exists('site_settings_load')) {
            $s = site_settings_load();
            $activate = (int) ($s['stress_auto_activate_rpm'] ?? $defaults['activate_rpm']);
            $release = (int) ($s['stress_auto_release_rpm'] ?? $defaults['release_rpm']);
            $release = min($release, max(1, $activate - 1));
            $defaults = [
                'enabled' => ! empty($s['stress_auto_enabled']),
                'activate_rpm' => $activate,
                'release_rpm' => $release,
                'hold_seconds' => max(60, (int) ($s['stress_auto_hold_minutes'] ?? 10) * 60),
                'window_seconds' => 60,
            ];
        }

        $activate = stress_mode_env_int('STRESS_AUTO_ACTIVATE_RPM', $defaults['activate_rpm'], 10, 10000);
        $release = stress_mode_env_int('STRESS_AUTO_RELEASE_RPM', $defaults['release_rpm'], 1, 10000);
        $release = min($release, max(1, $activate - 1));

        return [
            'enabled' => stress_mode_env_bool('STRESS_AUTO_ENABLED', $defaults['enabled']),
            'activate_rpm' => $activate,
            'release_rpm' => $release,
            'hold_seconds' => stress_mode_env_int(
                'STRESS_AUTO_HOLD_MINUTES',
                (int) ($defaults['hold_seconds'] / 60),
                1,
                1440
            ) * 60,
            'window_seconds' => 60,
        ];
    }
}

if (! function_exists('stress_mode_auto_state_path')) {
    function stress_mode_auto_state_path(): string
    {
        $dir = dirname(__DIR__).'/logs';
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir.'/stress_mode_auto.json';
    }
}

if (! function_exists('stress_mode_auto_load_state')) {
    /**
     * @return array{active_until?: int, triggered_at?: int, low_since?: int, peak_rpm?: int}
     */
    function stress_mode_auto_load_state(): array
    {
        $path = stress_mode_auto_state_path();
        if (! is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}

if (! function_exists('stress_mode_auto_save_state')) {
    /**
     * @param array<string, int> $state
     */
    function stress_mode_auto_save_state(array $state): void
    {
        @file_put_contents(stress_mode_auto_state_path(), json_encode($state), LOCK_EX);
    }
}

if (! function_exists('stress_mode_auto_clear_state')) {
    function stress_mode_auto_clear_state(): void
    {
        $path = stress_mode_auto_state_path();
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

if (! function_exists('stress_mode_is_auto_active')) {
    function stress_mode_is_auto_active(): bool
    {
        $state = stress_mode_auto_load_state();
        $until = (int) ($state['active_until'] ?? 0);

        return $until > time();
    }
}

if (! function_exists('stress_mode_auto_tick')) {
    /**
     * Misst Request-Last und schaltet den automatischen Schutzmodus ein/aus.
     * Einmal pro Request aus bootstrap aufrufen (vor enforce).
     */
    function stress_mode_auto_tick(): void
    {
        if (stress_mode_env_forced()) {
            return;
        }

        $config = stress_mode_auto_config();
        if (! $config['enabled']) {
            return;
        }

        if (! function_exists('rate_limit_record_and_count')) {
            require_once __DIR__.'/rate_limit.php';
        }

        $rpm = rate_limit_record_and_count('stress_site_load', $config['window_seconds'], '_global');
        $now = time();
        $state = stress_mode_auto_load_state();
        $peak = max($rpm, (int) ($state['peak_rpm'] ?? 0));
        $state['peak_rpm'] = $peak;

        $manualOn = function_exists('site_settings_load')
            && ! empty(site_settings_load()['stress_mode_enabled']);

        if ($manualOn) {
            stress_mode_auto_clear_state();

            return;
        }

        $activeUntil = (int) ($state['active_until'] ?? 0);
        $isActive = $activeUntil > $now;

        if (! $isActive) {
            if ($rpm >= $config['activate_rpm']) {
                $state = [
                    'active_until' => $now + $config['hold_seconds'],
                    'triggered_at' => $now,
                    'low_since' => 0,
                    'peak_rpm' => $rpm,
                ];
                stress_mode_auto_save_state($state);
            } else {
                stress_mode_auto_clear_state();
            }

            return;
        }

        $triggeredAt = (int) ($state['triggered_at'] ?? $now);
        $minHoldEnd = $triggeredAt + $config['hold_seconds'];

        if ($rpm <= $config['release_rpm']) {
            if (empty($state['low_since'])) {
                $state['low_since'] = $now;
            }
        } else {
            $state['low_since'] = 0;
            if ($now >= $activeUntil) {
                $state['active_until'] = $now + $config['hold_seconds'];
            }
        }

        $lowSince = (int) ($state['low_since'] ?? 0);
        $lowLongEnough = $lowSince > 0 && ($now - $lowSince) >= 120;

        if ($now >= $minHoldEnd && $lowLongEnough && $rpm <= $config['release_rpm']) {
            stress_mode_auto_clear_state();

            return;
        }

        if ($now >= $activeUntil && $rpm > $config['release_rpm']) {
            $state['active_until'] = $now + $config['hold_seconds'];
            $state['triggered_at'] = $now;
            $state['low_since'] = 0;
        }

        stress_mode_auto_save_state($state);
    }
}

if (! function_exists('stress_mode_auto_status')) {
    /**
     * @return array{enabled: bool, active: bool, rpm: int, active_until: int, config: array<string, int|bool>}
     */
    function stress_mode_auto_status(): array
    {
        $config = stress_mode_auto_config();
        $state = stress_mode_auto_load_state();
        $rpm = 0;
        if (! function_exists('rate_limit_storage_path')) {
            require_once __DIR__.'/rate_limit.php';
        }
        if (function_exists('rate_limit_storage_path')) {
            $path = rate_limit_storage_path('stress_site_load');
            $now = time();
            $cutoff = $now - 60;
            if (is_file($path)) {
                $raw = @file_get_contents($path);
                $decoded = is_string($raw) ? json_decode($raw, true) : null;
                if (is_array($decoded) && isset($decoded['_global']) && is_array($decoded['_global'])) {
                    foreach ($decoded['_global'] as $ts) {
                        if (is_int($ts) && $ts >= $cutoff) {
                            $rpm++;
                        }
                    }
                }
            }
        }

        return [
            'enabled' => $config['enabled'],
            'active' => stress_mode_is_auto_active(),
            'rpm' => $rpm,
            'active_until' => (int) ($state['active_until'] ?? 0),
            'peak_rpm' => (int) ($state['peak_rpm'] ?? 0),
            'config' => $config,
        ];
    }
}

if (! function_exists('stress_mode_is_manual_active')) {
    function stress_mode_is_manual_active(): bool
    {
        if (! function_exists('site_settings_load')) {
            return false;
        }

        return ! empty(site_settings_load()['stress_mode_enabled']);
    }
}

if (! function_exists('stress_mode_is_active')) {
    function stress_mode_is_active(): bool
    {
        if (stress_mode_env_forced()) {
            return true;
        }

        if (stress_mode_is_manual_active()) {
            return true;
        }

        return stress_mode_is_auto_active();
    }
}

if (! function_exists('stress_mode_active_reason')) {
    /** @return 'env'|'manual'|'auto'|null */
    function stress_mode_active_reason(): ?string
    {
        if (stress_mode_env_forced()) {
            return 'env';
        }
        if (stress_mode_is_manual_active()) {
            return 'manual';
        }
        if (stress_mode_is_auto_active()) {
            return 'auto';
        }

        return null;
    }
}

if (! function_exists('stress_mode_blocks_guest_content')) {
    function stress_mode_blocks_guest_content(): bool
    {
        if (! stress_mode_is_active()) {
            return false;
        }

        return ! authz_is_logged_in();
    }
}

if (! function_exists('stress_mode_allowed_pages')) {
    /**
     * @return string[]
     */
    function stress_mode_allowed_pages(): array
    {
        return [
            'home',
            'login',
            'register',
            'account',
            'two_factor',
            'verify_registration_request',
            'impressum',
            'datenschutz',
            'nutzungsbedingungen',
            '404',
        ];
    }
}

if (! function_exists('stress_mode_protected_pages')) {
    /**
     * @return string[]
     */
    function stress_mode_protected_pages(): array
    {
        return [
            'project_grid',
            'project_detail',
            'create_project',
            'edit_project',
        ];
    }
}

if (! function_exists('stress_mode_page_is_protected')) {
    function stress_mode_page_is_protected(string $page): bool
    {
        return in_array($page, stress_mode_protected_pages(), true);
    }
}

if (! function_exists('stress_mode_request_is_protected')) {
    function stress_mode_request_is_protected(): bool
    {
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script === 'media.php') {
            return true;
        }

        if (! function_exists('authz_current_page_key')) {
            return false;
        }

        return stress_mode_page_is_protected(authz_current_page_key());
    }
}

if (! function_exists('stress_mode_should_skip_enforcement')) {
    function stress_mode_should_skip_enforcement(): bool
    {
        if (function_exists('legacy_is_admin_script_request') && legacy_is_admin_script_request()) {
            return true;
        }

        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if (in_array($script, ['bridge_auth.php', 'bridge_auth_2fa.php', 'bridge_register.php', 'laravel_handoff.php'], true)) {
            return true;
        }

        if (! function_exists('authz_current_page_key')) {
            return false;
        }

        $page = authz_current_page_key();

        return in_array($page, stress_mode_allowed_pages(), true);
    }
}

if (! function_exists('stress_mode_safe_next_url')) {
    function stress_mode_safe_next_url(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($uri === '' || $uri[0] !== '/') {
            return '';
        }
        if (str_contains($uri, '..')) {
            return '';
        }
        if (! str_contains($uri, 'index.php') && basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'media.php') {
            return '';
        }

        return $uri;
    }
}

if (! function_exists('stress_mode_denied_redirect')) {
    function stress_mode_denied_redirect(): void
    {
        $reason = stress_mode_active_reason();
        $message = 'Hohe Last — bitte anmelden, um Projekte zu sehen.';
        if ($reason === 'auto') {
            $message = 'Hohe Last auf der Website — bitte anmelden, um Projekte zu sehen.';
        }

        if (function_exists('request_is_ajax') && request_is_ajax()) {
            if (! headers_sent()) {
                http_response_code(403);
                header('Content-Type: application/json; charset=UTF-8');
            }
            echo json_encode([
                'ok' => false,
                'error' => 'stress',
                'message' => $message,
            ]);
            exit;
        }

        if (! function_exists('legacy_index_url')) {
            require_once __DIR__.'/app_env.php';
        }

        $params = ['page' => 'login', 'err' => 'stress'];
        if ($reason === 'auto') {
            $params['err'] = 'stress_auto';
        }
        $next = stress_mode_safe_next_url();
        if ($next !== '') {
            $params['next'] = $next;
        }

        header('Location: '.legacy_index_url($params));
        exit;
    }
}

if (! function_exists('stress_mode_enforce_for_current_request')) {
    function stress_mode_enforce_for_current_request(): void
    {
        if (! stress_mode_blocks_guest_content()) {
            return;
        }

        if (stress_mode_should_skip_enforcement()) {
            return;
        }

        if (! stress_mode_request_is_protected()) {
            return;
        }

        stress_mode_denied_redirect();
    }
}
