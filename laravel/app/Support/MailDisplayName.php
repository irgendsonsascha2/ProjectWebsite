<?php

namespace App\Support;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Absender-Anzeigename ohne Anführungszeichen im From-Header (MailHog/UI).
 */
final class MailDisplayName
{
    public static function sanitize(string $name): string
    {
        $name = trim($name);
        $name = trim($name, "\"' \t\n\r");
        $name = str_replace(['"', "'"], '', $name);

        return trim($name);
    }

    public static function applyToMessage(Email $message): void
    {
        $from = $message->getFrom();
        if ($from === []) {
            return;
        }

        $address = $from[0];
        $name = self::sanitize($address->getName());
        $email = $address->getAddress();

        if ($name === $address->getName()) {
            return;
        }

        if ($name === '') {
            $message->from($email);

            return;
        }

        $message->from(new Address($email, $name));
    }

    public static function listen(MessageSending $event): void
    {
        self::applyToMessage($event->message);
    }
}
