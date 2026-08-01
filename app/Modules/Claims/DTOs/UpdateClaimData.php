<?php

declare(strict_types=1);

namespace App\Modules\Claims\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification partielle d'une réclamation.
 *
 * C'est ici que se renseignent les champs de résolution. `organization_id`,
 * `customer_id`, `created_by` et `created_at` n'y figurent pas : déplacer une
 * réclamation d'un client à l'autre, ou réécrire sa date d'ouverture, ferait
 * perdre la trace du dossier d'origine.
 */
final readonly class UpdateClaimData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'order_id' => 'orderId',
        'order_service_id' => 'orderServiceId',
        'tour_id' => 'tourId',
        'title' => 'title',
        'description' => 'description',
        'claim_type' => 'claimType',
        'cause' => 'cause',
        'decision' => 'decision',
        'follow_up' => 'followUp',
        'result' => 'result',
        'cost' => 'cost',
        'status' => 'status',
        'responsible_user_id' => 'responsibleUserId',
        'closed_at' => 'closedAt',
    ];

    public function __construct(public PartialAttributes $attributes) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(PartialAttributes::fromValidated($validated, self::MAPPING));
    }
}
