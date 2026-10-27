<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Claims;

/**
 * Règles partagées des réclamations du portail.
 *
 * Les champs internes du diagramme `Claim` sont **interdits**, pas ignorés :
 * un client qui envoie `status`, `cost` ou `customerId` reçoit un 422 qui le
 * dit. Ignorer en silence laisserait croire qu'une valeur a été prise en compte.
 */
final class CustomerClaimRules
{
    /** Champs gérés par le backend ou le transporteur, jamais par le client. */
    public const array INTERNAL = [
        'id', 'customerId', 'organizationId', 'tourId', 'cause', 'decision', 'followUp',
        'result', 'cost', 'status', 'createdBy', 'responsibleUserId', 'createdAt', 'closedAt',
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function prohibited(array $extra = []): array
    {
        return array_fill_keys([...self::INTERNAL, ...$extra], ['prohibited']);
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            '*.prohibited' => 'Ce champ est géré par votre transporteur.',
            'title.required' => 'Donnez un titre à la réclamation.',
            'claimType.required' => 'Indiquez le type de réclamation.',
            'orderId.required_with' => 'Choisissez la commande du service concerné.',
        ];
    }
}
