<?php

use MongoDB\BSON\UTCDateTime;

/**
 * Startet die 2FA-Einrichtung (Secret in Session, Backup-Anzeige zurücksetzen).
 */
function two_factor_begin_setup(): string
{
    require_once __DIR__.'/two_factor.php';

    $secret = two_factor_generate_secret();
    $_SESSION['two_factor_setup_secret'] = $secret;
    unset($_SESSION['two_factor_display_backup_codes']);

    return $secret;
}

/**
 * Ob beim Laden der 2FA-Seite automatisch ein Setup-Secret erzeugt werden soll.
 */
function two_factor_should_auto_begin(string $userId): bool
{
    require_once __DIR__.'/two_factor.php';

    $accountUser = $userId !== '' ? two_factor_find_user_by_id($userId) : null;
    if (two_factor_user_enabled($accountUser)) {
        return false;
    }

    $pending = $_SESSION['two_factor_display_backup_codes'] ?? null;
    if (is_array($pending) && count(array_filter($pending, 'is_string')) > 0) {
        return false;
    }

    if ((string) ($_SESSION['two_factor_setup_secret'] ?? '') !== '') {
        return false;
    }

    return true;
}

function two_factor_maybe_auto_begin(string $userId): void
{
    if (two_factor_should_auto_begin($userId)) {
        two_factor_begin_setup();
    }
}

/**
 * POST-Verarbeitung für die 2FA-Seite (pages/two_factor.php).
 *
 * @return array{
 *   message: string,
 *   messageClass: string,
 *   twoFactorEnabled: bool,
 *   twoFactorSetupSecret: string,
 *   displayBackupCodes: list<string>|null,
 *   accountEmail: string
 * }
 */
function two_factor_handle_post(string $userId): array
{
    require_once __DIR__.'/two_factor.php';

    $message = '';
    $messageClass = 'alert';
    $accountUser = $userId !== '' ? two_factor_find_user_by_id($userId) : null;
    $twoFactorEnabled = two_factor_user_enabled($accountUser);
    $twoFactorSetupSecret = (string) ($_SESSION['two_factor_setup_secret'] ?? '');
    $displayBackupCodes = $_SESSION['two_factor_display_backup_codes'] ?? null;
    if (is_array($displayBackupCodes)) {
        $displayBackupCodes = array_values(array_filter($displayBackupCodes, 'is_string'));
    } else {
        $displayBackupCodes = null;
    }

    $accountEmail = '';
    if (is_array($accountUser)) {
        $accountEmail = (string) ($accountUser['email'] ?? $_SESSION['email'] ?? '');
    } elseif (is_object($accountUser)) {
        $accountEmail = (string) ($accountUser->email ?? $_SESSION['email'] ?? '');
    } else {
        $accountEmail = (string) ($_SESSION['email'] ?? '');
    }

    if (isset($_POST['two_factor_begin'])) {
        $twoFactorSetupSecret = two_factor_begin_setup();
        $displayBackupCodes = null;
        $message = '✅ QR-Code bereit — scanne ihn mit deiner Authenticator-App und bestätige mit einem 6-stelligen Code.';
        $messageClass = 'alert alert--success';
    }

    if (isset($_POST['two_factor_cancel_setup'])) {
        unset($_SESSION['two_factor_setup_secret']);
        $twoFactorSetupSecret = '';
        $message = 'Einrichtung abgebrochen.';
        $messageClass = 'alert';
    }

    if (isset($_POST['two_factor_confirm']) && $twoFactorSetupSecret !== '') {
        $code = trim((string) ($_POST['totp_code'] ?? ''));
        if (! two_factor_verify_totp($twoFactorSetupSecret, $code)) {
            $message = '❌ Code ungültig — bitte erneut versuchen.';
            $messageClass = 'alert alert--error';
        } else {
            $backup = two_factor_generate_backup_codes();
            $now = new UTCDateTime();
            if (two_factor_update_user($userId, [
                'two_factor_enabled' => true,
                'two_factor_totp_secret' => $twoFactorSetupSecret,
                'two_factor_backup_codes' => $backup['hashed'],
                'two_factor_confirmed_at' => $now,
            ])) {
                unset($_SESSION['two_factor_setup_secret']);
                $twoFactorSetupSecret = '';
                $_SESSION['two_factor_display_backup_codes'] = $backup['plain'];
                $displayBackupCodes = $backup['plain'];
                $twoFactorEnabled = true;
                $message = '✅ Zwei-Faktor-Authentifizierung ist aktiv. Speichere die Backup-Codes sicher.';
                $messageClass = 'alert alert--success';
            } else {
                $message = '❌ Konnte 2FA nicht speichern.';
                $messageClass = 'alert alert--error';
            }
        }
    }

    if (isset($_POST['two_factor_disable']) && $twoFactorEnabled && $accountUser !== null) {
        $code = trim((string) ($_POST['totp_code'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $hash = is_array($accountUser) ? (string) ($accountUser['password'] ?? '') : (string) ($accountUser->password ?? '');
        $secret = is_array($accountUser) ? (string) ($accountUser['two_factor_totp_secret'] ?? '') : (string) ($accountUser->two_factor_totp_secret ?? '');
        if ($password === '' || ! password_verify($password, $hash)) {
            $message = '❌ Passwort falsch.';
            $messageClass = 'alert alert--error';
        } elseif (! two_factor_verify_totp($secret, $code)) {
            $message = '❌ Authenticator-Code ungültig.';
            $messageClass = 'alert alert--error';
        } elseif (two_factor_update_user($userId, [
            'two_factor_enabled' => false,
            'two_factor_totp_secret' => null,
            'two_factor_backup_codes' => [],
            'two_factor_confirmed_at' => null,
        ])) {
            $twoFactorEnabled = false;
            unset($_SESSION['two_factor_setup_secret'], $_SESSION['two_factor_display_backup_codes']);
            $displayBackupCodes = null;
            $twoFactorSetupSecret = '';
            $message = '✅ Zwei-Faktor-Authentifizierung deaktiviert.';
            $messageClass = 'alert alert--success';
        }
    }

    if (isset($_POST['two_factor_regenerate_backup']) && $twoFactorEnabled && $accountUser !== null) {
        $code = trim((string) ($_POST['totp_code'] ?? ''));
        $secret = is_array($accountUser) ? (string) ($accountUser['two_factor_totp_secret'] ?? '') : (string) ($accountUser->two_factor_totp_secret ?? '');
        if (! two_factor_verify_totp($secret, $code)) {
            $message = '❌ Authenticator-Code ungültig.';
            $messageClass = 'alert alert--error';
        } else {
            $backup = two_factor_generate_backup_codes();
            if (two_factor_update_user($userId, ['two_factor_backup_codes' => $backup['hashed']])) {
                $_SESSION['two_factor_display_backup_codes'] = $backup['plain'];
                $displayBackupCodes = $backup['plain'];
                $message = '✅ Neue Backup-Codes erzeugt — alte Codes sind ungültig.';
                $messageClass = 'alert alert--success';
            }
        }
    }

    if (isset($_POST['two_factor_dismiss_backup'])) {
        unset($_SESSION['two_factor_display_backup_codes']);
        $displayBackupCodes = null;
    }

    return [
        'message' => $message,
        'messageClass' => $messageClass,
        'twoFactorEnabled' => $twoFactorEnabled,
        'twoFactorSetupSecret' => $twoFactorSetupSecret,
        'displayBackupCodes' => $displayBackupCodes,
        'accountEmail' => $accountEmail,
    ];
}
