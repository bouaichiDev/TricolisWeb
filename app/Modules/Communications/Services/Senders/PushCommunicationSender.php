<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services\Senders;

/**
 * Notification push — aucun service de push n'est raccordé au projet.
 */
final readonly class PushCommunicationSender extends UnconfiguredChannelSender
{
    protected function missingRequirement(): string
    {
        return 'aucun service de notification push';
    }
}
