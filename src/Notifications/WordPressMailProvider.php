<?php

declare(strict_types=1);

namespace ABP\Notifications;

final class WordPressMailProvider implements MailProvider
{
    public function send(string $recipient, string $subject, string $html): bool
    {
        return wp_mail($recipient, $subject, $html, array('Content-Type: text/html; charset=UTF-8'));
    }
}
