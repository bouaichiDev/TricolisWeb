<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Mapping;

/**
 * La partie « prestations » des champs cibles d'import : destination, client
 * final, service et contacts.
 *
 * Séparée de `ImportTargetFields` pour tenir en un fichier lisible ; c'est la
 * section la plus exigeante d'un import, `services` étant `required|min:1` et
 * presque tous ses champs obligatoires.
 */
final class ImportServiceTargetFields
{
    /** @var array<string, list<array{0: string, 1: string, 2?: string}>> */
    public const array GROUPS = [
        'destination' => [
            ['services[].serviceCode', 'required', 'code d’une prestation de l’organisation'],
            ['services[].addressCode', 'conditional', 'code d’un point du donneur d’ordre ; sinon recipient'],
        ],
        'recipient' => [
            ['services[].recipient.firstName', 'required', 'max 255'],
            ['services[].recipient.lastName', 'required', 'max 255'],
            ['services[].recipient.email', 'required', 'courriel'],
            ['services[].recipient.phone', 'required', 'max 255'],
            ['services[].recipient.mobile', 'optional', 'max 255'],
            ['services[].recipient.company', 'optional', 'max 255'],
            ['services[].recipient.addressLine1', 'required', 'rue et numéro'],
            ['services[].recipient.addressLine2', 'optional', 'max 255'],
            ['services[].recipient.postalCode', 'required', 'max 64'],
            ['services[].recipient.city', 'required', 'max 255'],
            ['services[].recipient.country', 'required', '2 lettres : CH, FR…'],
            ['services[].recipient.instructions', 'optional', 'consigne d’accès'],
        ],
        'services' => [
            ['services[].serviceNumber', 'required', 'max 255'],
            ['services[].sequence', 'required', 'entier ≥ 1'],
            ['services[].requestedDate', 'required', 'date'],
            ['services[].requestedFrom', 'optional', 'date'],
            ['services[].requestedTo', 'optional', 'date ≥ requestedFrom'],
            ['services[].quantity', 'required', '> 0'],
            ['services[].unit', 'required', 'max 32'],
            ['services[].requiredTimeMinutes', 'required', 'entier ≥ 0'],
            ['services[].remainingTimeMinutes', 'required', 'entier ≥ 0'],
            ['services[].weight', 'required', '≥ 0'],
            ['services[].volume', 'required', '≥ 0'],
            ['services[].packageCount', 'required', 'entier ≥ 0'],
            ['services[].customerUnitPrice', 'optional', '≥ 0'],
            ['services[].customerTotalPrice', 'optional', '≥ 0'],
            ['services[].providerUnitCost', 'optional', '≥ 0'],
            ['services[].providerTotalCost', 'optional', '≥ 0'],
            ['services[].instructions', 'optional'],
            ['services[].packages[].packageKey', 'optional', 'packages[].key du colis traité'],
            ['services[].packages[].quantity', 'optional', '> 0'],
            ['services[].packages[].handlingInstructions', 'optional'],
        ],
        'contacts' => [
            ['services[].contacts[].firstName', 'conditional', 'obligatoire sans contact existant'],
            ['services[].contacts[].lastName', 'optional', 'max 255'],
            ['services[].contacts[].phone', 'optional', 'max 255'],
            ['services[].contacts[].mobile', 'optional', 'max 255'],
            ['services[].contacts[].email', 'optional', 'courriel'],
            ['services[].contacts[].contactRole', 'optional', 'load, delivery, billing, operations, emergency, other'],
            ['services[].contacts[].isPrimary', 'optional', 'booléen'],
        ],
    ];
}
