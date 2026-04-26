<?php

/**
 * Mail helper for local/dev and small deployments.
 *
 * - Default: PHP `mail()` (system MTA must be configured).
 * - If `MAIL_SMTP_HOST` is set: plain SMTP (TLS nicht implementiert; für lokalen MailHog/Posteingang).
 * - Schreibt best-effort eine Kopie nach `logs/mail.log` (für lokalen Test ohne echten Postausgang).
 *   Standard: sensible Teile (Token, Registrierungscodes) werden redigiert; mit `MAIL_LOG_REDACT_SECRETS=0` voller Body.
 */

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
            $root = realpath(__DIR__ . '/..');
            if (!$root) {
                return;
            }
            $redact = (string)(getenv('MAIL_LOG_REDACT_SECRETS') ?: '1') !== '0';
            $logBody = $redact ? redact_mail_body_for_log($body) : $body;
            $logDir = $root . '/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $viaLine = $via ? ('via: ' . $via . "\n") : '';
            $line = "----\n"
                . 'at: ' . (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM) . "\n"
                . $viaLine
                . 'to: ' . $to . "\n"
                . 'subject: ' . $subject . "\n"
                . 'sent: ' . ($sent ? 'true' : 'false') . "\n"
                . "\n"
                . $logBody . "\n";
            @file_put_contents($logDir . '/mail.log', $line, FILE_APPEND);
        } catch (Throwable $e) {
            // ignore
        }
    }
}

if (!function_exists('send_via_smtp')) {
    /**
     * Plain SMTP (Port typisch 1025 für MailHog, 25 offen) — kein STARTTLS/Auth.
     */
    function send_via_smtp(
        string $host,
        int $port,
        string $to,
        string $from,
        string $subject,
        string $body,
        ?string $replyTo
    ): bool {
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client(
            'tcp://' . $host . ':' . $port,
            $errno,
            $errstr,
            8,
            STREAM_CLIENT_CONNECT
        );
        if (!is_resource($fp)) {
            return false;
        }
        stream_set_timeout($fp, 10);

        $read = function () use ($fp): string {
            $buf = '';
            while (true) {
                $line = fgets($fp, 8192);
                if ($line === false) {
                    break;
                }
                $line = rtrim($line, "\r\n");
                $buf .= $line . "\n";
                if (strlen($line) >= 4 && $line[3] === '-') {
                    continue;
                }
                break;
            }
            return $buf;
        };
        $send = function (string $s) use ($fp): void {
            fwrite($fp, $s);
        };
        $expect2xx = function (string $label, string $response) use ($read): bool {
            $r = $read();
            if ($r === '' || $r[0] !== '2') {
                return false;
            }
            return true;
        };

        $greeting = $read();
        if ($greeting === '' || (isset($greeting[0]) && $greeting[0] !== '2')) {
            fclose($fp);
            return false;
        }
        $send("EHLO localhost\r\n");
        if (!$expect2xx('ehlo', $greeting)) {
            fclose($fp);
            return false;
        }
        $send('MAIL FROM:<' . $from . ">\r\n");
        if (!$expect2xx('mail from', '')) {
            fclose($fp);
            return false;
        }
        $send('RCPT TO:<' . $to . ">\r\n");
        if (!$expect2xx('rcpt to', '')) {
            fclose($fp);
            return false;
        }
        $send("DATA\r\n");
        $dataPrompt = $read();
        if ($dataPrompt === '' || !isset($dataPrompt[0]) || $dataPrompt[0] !== '3') {
            fclose($fp);
            return false;
        }
        $encodedSubj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $dataHeaders = "From: {$from}\r\n"
            . "To: {$to}\r\n"
            . "Subject: {$encodedSubj}\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=utf-8\r\n";
        if ($replyTo) {
            $dataHeaders .= 'Reply-To: ' . $replyTo . "\r\n";
        }
        $norm = str_replace(["\r\n", "\r"], "\n", $body);
        $escaped = preg_replace('/^\./m', '..', $norm) ?? $norm;
        $payload = $dataHeaders . "\r\n" . str_replace("\n", "\r\n", $escaped) . "\r\n.\r\n";
        $send($payload);
        if (!$expect2xx('data', '')) {
            fclose($fp);
            return false;
        }
        $send("QUIT\r\n");
        fclose($fp);
        return true;
    }
}

if (!function_exists('send_plain_mail')) {
    function send_plain_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool
    {
        $from = (string)(getenv('MAIL_FROM_EMAIL') ?: 'no-reply@localhost');
        $from = trim($from) !== '' ? trim($from) : 'no-reply@localhost';

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=utf-8';
        $headers[] = 'From: ' . $from;
        if ($replyTo) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $smtpHost = trim((string)(getenv('MAIL_SMTP_HOST') ?: ''));
        $sent = false;
        $via = 'mail()';

        if ($smtpHost !== '') {
            $port = (int)(getenv('MAIL_SMTP_PORT') ?: 25);
            if ($port < 1 || $port > 65535) {
                $port = 25;
            }
            $via = 'smtp://' . $smtpHost . ':' . $port;
            $sent = send_via_smtp($smtpHost, $port, $to, $from, $subject, $body, $replyTo);
        } else {
            $sent = (bool)@mail($to, $subject, $body, implode("\r\n", $headers));
        }

        mail_append_debug_log($to, $subject, $body, $sent, $via);

        return $sent;
    }
}
