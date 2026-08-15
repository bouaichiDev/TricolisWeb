<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services\Senders;

use App\Modules\Communications\Enums\CommunicationChannel;
use Illuminate\Contracts\Container\Container;

/**
 * Choisit le transporteur correspondant au canal.
 *
 * La correspondance est exhaustive sur l'enum : ajouter un canal — ce que le
 * diagramme interdit — provoquerait une erreur de compilation du `match`, pas
 * un envoi silencieusement perdu.
 *
 * Les transporteurs sont résolus par le conteneur : un test peut en substituer
 * un par un fake sans toucher au Job (§26, §45).
 */
final readonly class CommunicationSenderRegistry
{
    public function __construct(private Container $container) {}

    public function for(CommunicationChannel $channel): CommunicationSender
    {
        $class = match ($channel) {
            CommunicationChannel::EMAIL => EmailCommunicationSender::class,
            CommunicationChannel::SMS => SmsCommunicationSender::class,
            CommunicationChannel::WHATSAPP => WhatsappCommunicationSender::class,
            CommunicationChannel::PUSH_NOTIFICATION => PushCommunicationSender::class,
            CommunicationChannel::INTERNAL_NOTIFICATION => InternalCommunicationSender::class,
        };

        /** @var CommunicationSender $sender */
        $sender = $this->container->make($class);

        return $sender;
    }
}
