<?php

declare(strict_types=1);

namespace App\Modules\Templates\DTOs;

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Templates\Enums\TemplateType;

/**
 * Ce qu'on sait au moment de choisir un modèle.
 *
 * Un objet plutôt que six arguments : l'ordre de `?string $customerId,
 * ?string $serviceId` s'inverse silencieusement à la première relecture, et
 * l'erreur ne se voit qu'au moment où le mauvais modèle part chez le client.
 *
 * Les champs facultatifs sont facultatifs pour de vrai : une facture n'a ni
 * canal ni service, une communication globale n'a pas de client.
 */
final readonly class TemplateQuery
{
    public function __construct(
        public string $organizationId,
        public TemplateType $templateType,
        public ?string $customerId = null,
        public ?string $serviceId = null,
        public ?CommunicationChannel $channel = null,
        public ?string $language = null,
    ) {}

    /** La requête d'une facture : par client, sans canal ni service. */
    public static function forInvoice(string $organizationId, string $customerId): self
    {
        return new self(
            organizationId: $organizationId,
            templateType: TemplateType::INVOICE,
            customerId: $customerId,
        );
    }
}
