<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Shared\Database\MorphMap;

/**
 * L'adresse du destinataire final, reprise du fichier.
 *
 * Un code d'adresse ne convient qu'aux points **récurrents** : un magasin, un
 * quai, un chantier, que le client a enregistrés une fois. Une large part du
 * transport ne fonctionne pas ainsi — chaque commande va chez quelqu'un
 * d'autre, et son adresse n'existe nulle part avant le fichier.
 *
 * La correspondance porte alors l'adresse **elle-même**, et l'import la crée :
 *
 * ```json
 * "services": [{
 *   "address": { "addressLine1": "DEST_RUE", "city": "DEST_VILLE" }
 * }]
 * ```
 *
 * ## À qui elle appartient
 *
 * **Ni au client, ni à l'un de ses sites.** Le carnet d'adresses du donneur
 * d'ordre décrit **ses** lieux à lui ; y verser mille destinataires ponctuels
 * le rendrait inutilisable, et laisserait croire que le client travaille avec
 * des gens qu'il ne connaît pas. Le lien qui compte est celui de la prestation,
 * `order_services.address_id` : c'est la commande qui va là, pas le client qui
 * y a un site.
 *
 * Une liaison est tout de même écrite, vers l'**organisation**. Elle n'est pas
 * décorative : `addresses` ne porte pas d'`organization_id`, et
 * `OrderScopeGuard::address()` refuse toute adresse qu'aucune liaison ne
 * rattache à l'organisation active — c'est ce qui empêche une organisation
 * d'accrocher à sa commande l'adresse d'une autre. Sans elle, la commande
 * serait refusée sur `services.*.addressId` un instant après la création.
 *
 * C'est la forme que le produit donne déjà à une adresse n'appartenant à aucune
 * entité en particulier : `EntityLinkResolver` choisit la même quand un
 * formulaire n'en désigne pas. La liaison répond « cette organisation a le droit
 * de s'en servir » ; elle ne dit pas à qui l'adresse est.
 */
final readonly class ImportedRecipientAddress
{
    /**
     * Colonnes recopiables, et elles seules.
     *
     * Le tableau vient d'une correspondance qu'un administrateur écrit : le
     * passer tel quel au modèle laisserait écrire `status`, `is_default`, voire
     * n'importe quelle colonne ajoutée demain.
     *
     * `code` en est **absent**, même si le fichier en porte un : la recherche
     * par code passe par le rattachement au client, que cette adresse n'a pas.
     * Lui en donner un promettrait une réutilisation qui n'arrivera pas.
     *
     * @var array<string, string>
     */
    private const array COLUMNS = [
        'name' => 'name',
        'addressLine1' => 'address_line_1',
        'addressLine2' => 'address_line_2',
        'addressLine3' => 'address_line_3',
        'floor' => 'floor',
        'addressNumber' => 'address_number',
        'route' => 'route',
        'sublocality' => 'sublocality',
        'postalCode' => 'postal_code',
        'city' => 'city',
        'town' => 'town',
        'country' => 'country',
        'latitude' => 'latitude',
        'longitude' => 'longitude',
        'instructions' => 'instructions',
    ];

    /**
     * Ce qu'une adresse doit porter pour être livrable.
     *
     * Une adresse sans rue donnerait une commande qu'aucun planificateur ne
     * peut placer. La créer quand même reporterait le problème sur la tournée,
     * où plus rien ne dit d'où vient l'erreur.
     */
    public function isDeliverable(mixed $inline): bool
    {
        if (! is_array($inline)) {
            return false;
        }

        $line = $inline['addressLine1'] ?? null;

        return is_string($line) && trim($line) !== '';
    }

    /**
     * @param  array<string, mixed>  $inline
     * @return string l'identifiant de l'adresse créée
     */
    public function create(array $inline, string $organizationId): string
    {
        $attributes = [];

        foreach (self::COLUMNS as $field => $column) {
            $value = $inline[$field] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                $attributes[$column] = trim((string) $value);
            }
        }

        $address = Address::create($attributes);

        EntityAddress::create([
            'organization_id' => $organizationId,
            'address_id' => $address->id,
            'entity_type' => MorphMap::ORGANIZATION,
            'entity_id' => $organizationId,
        ]);

        return (string) $address->getKey();
    }
}
