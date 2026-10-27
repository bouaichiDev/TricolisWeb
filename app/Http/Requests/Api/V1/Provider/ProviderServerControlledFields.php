<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Provider;

/**
 * Les champs qu'un appel du portail fournisseur ne choisit **jamais**.
 *
 * Fournisseur et organisation viennent de la session, statut et identifiant
 * du serveur. Les refuser à la validation rend la tentative **visible** (422)
 * au lieu de l'ignorer en silence.
 */
final class ProviderServerControlledFields
{
    public const array TENANT = ['providerId', 'provider_id', 'organizationId', 'organization_id', 'status', 'id'];

    /**
     * @param  list<string>  $fields
     * @return array<string, list<string>>
     */
    public static function prohibit(array $fields = self::TENANT): array
    {
        return array_fill_keys($fields, ['prohibited']);
    }
}
