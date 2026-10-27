<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer;

/**
 * Les champs qu'un appel du portail ne choisit **jamais** (Phase 7, §20).
 *
 * Le serveur les impose — client et organisation depuis la session, statut
 * depuis le référentiel, prix et coûts depuis la tarification. Les Actions
 * du portail ne recopiaient déjà que des clés listées ; les refuser en plus
 * à la validation rend la tentative **visible** (422) au lieu de l'ignorer en
 * silence, comme le faisaient déjà réclamations et stock.
 */
final class ServerControlledFields
{
    /** Le périmètre : toujours celui de la session. */
    public const array TENANT = ['customerId', 'organizationId', 'customer_id', 'organization_id'];

    /** Ce qu'une commande du portail ne fixe pas. */
    public const array ORDER = [
        ...self::TENANT,
        'agencyId', 'depotId', 'source', 'status', 'orderNumber', 'internalRemark', 'currencyCode', 'createdBy', 'updatedBy',
        'lines.*.status', 'lines.*.purchasePrice', 'lines.*.sellingPrice',
        'services.*.status', 'services.*.customerUnitPrice', 'services.*.customerTotalPrice',
        'services.*.providerUnitCost', 'services.*.providerTotalCost',
    ];

    /** Ce qu'un administrateur client ne fixe pas pour un membre. */
    public const array MEMBER = [
        ...self::TENANT,
        'userId', 'status', 'isAdmin', 'is_admin', 'isOwner', 'is_owner', 'password', 'roleIds', 'permissions', 'scope',
    ];

    /**
     * @param  list<string>  $fields
     * @return array<string, list<string>>
     */
    public static function prohibit(array $fields): array
    {
        return array_fill_keys($fields, ['prohibited']);
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return ['*.prohibited' => 'Ce champ est géré par votre transporteur : il ne peut pas être fourni.'];
    }
}
