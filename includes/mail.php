<?php

/**
 * Mail helper for local/dev and small deployments.
 *
 * - Default: PHP `mail()` (system MTA must be configured).
 * - If `MAIL_SMTP_HOST` is set: SMTP (Plain für MailHog; STARTTLS/SSL + Auth für Produktion).
 * - Schreibt best-effort eine Kopie nach `logs/mail.log`.
 */

if (!function_exists('mail_smtp_encryption')) {
    /**
     * @return 'none'|'tls'|'ssl'
     */
    function mail_smtp_encryption(): string
    {
        $v = strtolower(trim((string) (getenv('MAIL_SMTP_ENCRYPTION') ?: '')));
        if (in_array($v, ['tls', 'starttls', 'ssl', 'smtps'], true)) {
            return $v === 'ssl' || $v === 'smtps' ? 'ssl' : 'tls';
        }

        return 'none';
    }
}

if (!function_exists('mail_smtp_requires_tls_in_production')) {
    function mail_smtp_requires_tls_in_production(): bool
    {
        if (!function_exists('app_is_production') || !app_is_production()) {
            return false;
        }
        $host = trim((string) (getenv('MAIL_SMTP_HOST') ?: ''));
        if ($host === '') {
            return false;
        }

        return mail_smtp_encryption() === 'none';
    }
}

if (!function_exists('redact_mail_body_for_log')) {
    function redact_mail_body_for_log(string $body): string
    {
        $out = $body;
        $out = preg_replace('/([?&])token=([^&\s#]+)/', '$1token=[REDACTED]', $out) ?? $out;
        $out = preg_replace('/([?&])reg_token=([^&\s#]+)/', '$1reg_token=[REDACTED]', $out) ?? $out;
        $out = preg_replace('/\b[0-9a-fA-F]{64}\b/', '[REDACTED_64HEX]', $out) ?? $out;
        $out = preg_replace('/^[0-9A-F]{16}$/m', '[REDACTED_CODE_LINE]', $out) ?? $out;

        return $out;
    }
}

if (!function_exists('mail_append_debug_log')) {
    function mail_append_debug_log(string $to, string $subject, string $body, bool $sent, ?string $via = null): void
    {
        try {
            $root = realpath(__DIR__.'/..');
            if (!$root) {
                return;
            }
            $redact = (string) (getenv('MAIL_LOG_REDACT_SECRETS') ?: '1') !== '0';
            $logBody = $redact ? redact_mail_body_for_log($body) : $body;
            $logDir = $root.'/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $viaLine = $via ? ('via: '.$via."\n") : '';
            $line = "----\n"
                .'at: '.(new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM)."\n"
                .$viaLine
                .'to: '.$to."\n"
                .'subject: '.$subject."\n"
                .'sent: '.($sent ? 'true' : 'false')."\n"
                ."\n"
                .$logBody."\n";
            @file_put_contents($logDir.'/mail.log', $line, FILE_APPEND);
        } catch (Throwable $e) {
            // ignore
        }
    }
}

if (!function_exists('smtp_read_response')) {
    function smtp_read_response($fp): string
    {
        $buf = '';
        while (true) {
            $line = fgets($fp, 8192);
            if ($line === false) {
                break;
            }
            $line = rtrim($line, "\r\n");
            $buf .= $line."\n";
            if (strlen($line) >= 4 && $line[3] === '-') {
                continue;
            }
            break;
        }

        return $buf;
    }
}

if (!function_exists('smtp_expect_code')) {
    function smtp_expect_code(string $response, string $codePrefix): bool
    {
        $first = strtok($response, "\n");

        return is_string($first) && str_starts_with($first, $codePrefix);
    }
}

if (!function_exists('smtp_send_line')) {
    function smtp_send_line($fp, string $line): void
    {
        fwrite($fp, $line);
    }
}

if (!function_exists('smtp_ehlo')) {
    function smtp_ehlo($fp, string $ehloHost = 'localhost'): bool
    {
        smtp_send_line($fp, 'EHLO '.$ehloHost."\r\n");
        $r = smtp_read_response($fp);

        return smtp_expect_code($r, '2');
    }
}

if (!function_exists('smtp_starttls')) {
    function smtp_starttls($fp): bool
    {
        smtp_send_line($fp, "STARTTLS\r\n");
        $r = smtp_read_response($fp);
        if (!smtp_expect_code($r, '2')) {
            return false;
        }
        $crypto = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($crypto !== true) {
            return false;
        }

        return smtp_ehlo($fp);
    }
}

if (!function_exists('smtp_auth_login')) {
    function smtp_auth_login($fp, string $user, string $pass): bool
    {
        smtp_send_line($fp, "AUTH LOGIN\r\n");
        $r = smtp_read_response($fp);
        if (!smtp_expect_code($r, '3')) {
            return false;
        }
        smtp_send_line($fp, base64_encode($user)."\r\n");
        $r = smtp_read_response($fp);
        if (!smtp_expect_code($r, '3')) {
            return false;
        }
        smtp_send_line($fp, base64_encode($pass)."\r\n");
        $r = smtp_read_response($fp);

        return smtp_expect_code($r, '2');
    }
}

if (!function_exists('send_via_smtp')) {
    /**
     * SMTP: Plain (MailHog), STARTTLS (typ. 587) oder implicit SSL (typ. 465).
     */
    function send_via_smtp(
        string $host,
        int $port,
        string $to,
        string $from,
        string $subject,
        string $body,
        ?string $replyTo,
        string $encryption = 'none',
        ?string $username = null,
        ?string $password = null
    ): bool {
        if (function_exists('mail_smtp_requires_tls_in_production') && mail_smtp_requires_tls_in_production()) {
            return false;
        }

        $errno = 0;
        $errstr = '';
        $target = ($encryption === 'ssl' ? 'ssl://' : 'tcp://').$host.':'.$port;
        $fp = @stream_socket_client($target, $errno, $errstr, 12, STREAM_CLIENT_CONNECT);
        if (!is_resource($fp)) {
            return false;
        }
        stream_set_timeout($fp, 15);

        $greeting = smtp_read_response($fp);
        if (!smtp_expect_code($greeting, '2')) {
            fclose($fp);

            return false;
        }

        if (!smtp_ehlo($fp)) {
            fclose($fp);

            return false;
        }

        if ($encryption === 'tls') {
            if (!smtp_starttls($fp)) {
                fclose($fp);

                return false;
            }
        }

        $user = $username !== null ? trim($username) : '';
        $pass = $password !== null ? (string) $password : '';
        if ($user !== '') {
            if (!smtp_auth_login($fp, $user, $pass)) {
                fclose($fp);

                return false;
            }
        }

        smtp_send_line($fp, 'MAIL FROM:<'.$from.">\r\n");
        if (!smtp_expect_code(smtp_read_response($fp), '2')) {
            fclose($fp);

            return false;
        }
        smtp_send_line($fp, 'RCPT TO:<'.$to.">\r\n");
        if (!smtp_expect_code(smtp_read_response($fp), '2')) {
            fclose($fp);

            return false;
        }
        smtp_send_line($fp, "DATA\r\n");
        if (!smtp_expect_code(smtp_read_response($fp), '3')) {
            fclose($fp);

            return false;
        }

        $encodedSubj = '=?UTF-8?B?'.base64_encode($subject).'?=';
        $dataHeaders = "From: {$from}\r\n"
            ."To: {$to}\r\n"
            ."Subject: {$encodedSubj}\r\n"
            ."MIME-Version: 1.0\r\n"
            ."Content-Type: text/plain; charset=utf-8\r\n";
        if ($replyTo) {
            $dataHeaders .= 'Reply-To: '.$replyTo."\r\n";
        }
        $norm = str_replace(["\r\n", "\r"], "\n", $body);
        $escaped = preg_replace('/^\./m', '..', $norm) ?? $norm;
        $payload = $dataHeaders."\r\n".str_replace("\n", "\r\n", $escaped)."\r\n.\r\n";
        smtp_send_line($fp, $payload);
        if (!smtp_expect_code(smtp_read_response($fp), '2')) {
            fclose($fp);

            return false;
        }
        smtp_send_line($fp, "QUIT\r\n");
        fclose($fp);

        return true;
    }
}

if (!function_exists('send_plain_mail')) {
    function send_plain_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool
    {
        $from = (string) (getenv('MAIL_FROM_EMAIL') ?: 'no-reply@localhost');
        $from = trim($from) !== '' ? trim($from) : 'no-reply@localhost';

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=utf-8';
        $headers[] = 'From: '.$from;
        if ($replyTo) {
            $headers[] = 'Reply-To: '.$replyTo;
        }

        $smtpHost = trim((string) (getenv('MAIL_SMTP_HOST') ?: ''));
        $sent = false;
        $via = 'mail()';

        if ($smtpHost !== '') {
            $port = (int) (getenv('MAIL_SMTP_PORT') ?: 25);
            if ($port < 1 || $port > 65535) {
                $port = 25;
            }
            $encryption = mail_smtp_encryption();
            $user = getenv('MAIL_SMTP_USERNAME');
            $pass = getenv('MAIL_SMTP_PASSWORD');
            $via = 'smtp://'.$smtpHost.':'.$port.' ('.$encryption.')';
            $sent = send_via_smtp(
                $smtpHost,
                $port,
                $to,
                $from,
                $subject,
                $body,
                $replyTo,
                $encryption,
                $user !== false ? (string) $user : null,
                $pass !== false ? (string) $pass : null
            );
        } else {
            $sent = (bool) @mail($to, $subject, $body, implode("\r\n", $headers));
        }

        mail_append_debug_log($to, $subject, $body, $sent, $via);

        return $sent;
    }
}
