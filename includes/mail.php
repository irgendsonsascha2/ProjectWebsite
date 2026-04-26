<?php

/**
 * Mail helper for local/dev environments.
 *
 * - Default uses PHP `mail()` (system MTA must be configured).
 * - Always writes a copy to `logs/mail.log` for debugging (best-effort).
 *
 * Note: Caller decides what to do when sending fails.
 */

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

        $sent = @mail($to, $subject, $body, implode("\r\n", $headers));

        // Best-effort debug log (do not fail the request if logging fails).
        try {
            $root = realpath(__DIR__ . '/..');
            if ($root) {
                $logDir = $root . '/logs';
                if (!is_dir($logDir)) {
                    @mkdir($logDir, 0755, true);
                }
                $line = "----\n"
                    . 'at: ' . (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM) . "\n"
                    . 'to: ' . $to . "\n"
                    . 'subject: ' . $subject . "\n"
                    . 'sent: ' . ($sent ? 'true' : 'false') . "\n"
                    . "\n"
                    . $body . "\n";
                @file_put_contents($logDir . '/mail.log', $line, FILE_APPEND);
            }
        } catch (Throwable $e) {
            // ignore
        }

        return (bool)$sent;
    }
}

