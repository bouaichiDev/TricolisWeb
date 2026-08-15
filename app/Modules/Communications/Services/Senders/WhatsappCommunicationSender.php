<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services\Senders;

/**
 * WhatsApp — aucun compte WhatsApp Business n'est raccordé au projet.
 */
final readonly class WhatsappCommunicationSender extends UnconfiguredChannelSender
{
    protected function missingRequirement(): string
    {
        return 'aucun compte WhatsApp Business';
    }
}
