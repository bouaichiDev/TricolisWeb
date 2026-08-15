<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services\Senders;

/**
 * SMS — aucun agrégateur n'est raccordé au projet.
 */
final readonly class SmsCommunicationSender extends UnconfiguredChannelSender
{
    protected function missingRequirement(): string
    {
        return 'aucun agrégateur SMS';
    }
}
