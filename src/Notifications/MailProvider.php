<?php

declare(strict_types=1);

namespace ABP\Notifications;

interface MailProvider
{
    public function send(string $recipient, string $subject, string $html): bool;
}
