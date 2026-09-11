<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Contacts\Models\AddressContact;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\EntityContact;
use App\Shared\Database\MorphMap;
use App\Shared\Enums\ContactRole;

/**
 * Le client final d'une prestation, créé depuis le fichier.
 *
 * Il en naît **deux lignes** : son adresse, dans `addresses`, et la personne à
 * joindre, dans `contacts` — reliées par `address_contacts` comme n'importe
 * quel contact de livraison. La prestation reçoit l'adresse, et une copie du
 * contact comme contact principal : c'est ce que lit le bon de livraison.
 *
 * Les coordonnées GPS ne sont pas cherchées ici — l'appel est distant, et un
 * fichier refusé ensuite l'aurait fait pour rien. `LocateImportedAddresses`
 * s'en charge une fois les commandes écrites.
 *
 * ## À qui ils appartiennent
 *
 * **Ni au donneur d'ordre, ni à l'un de ses sites.** Son carnet d'adresses
 * décrit **ses** lieux ; y verser mille destinataires ponctuels le rendrait
 * inutilisable. Le lien qui compte est celui de la prestation.
 *
 * Les liaisons écrites visent l'**organisation**, et elles ne sont pas
 * décoratives : `addresses` et `contacts` ne portent pas d'`organization_id`,
 * et `OrderScopeGuard` comme `CreateOrderServices` refusent ce qu'aucune liaison
 * ne rattache à l'organisation active. Elles disent « cette organisation peut
 * s'en servir », pas à qui la chose est.
 */
final readonly class ImportedRecipient
{
    /**
     * Colonnes d'adresse recopiables, et elles seules : le tableau vient d'une
     * correspondance qu'un administrateur écrit, et le passer tel quel au
     * modèle laisserait écrire `status` ou `is_default`.
     *
     * `code` en est **absent** : la recherche par code passe par le
     * rattachement au donneur d'ordre, que cette adresse n'a pas.
     *
     * @var array<string, string>
     */
    private const array ADDRESS = [
        'addressLine1' => 'address_line_1',
        'addressLine2' => 'address_line_2',
        'postalCode' => 'postal_code',
        'city' => 'city',
        'country' => 'country',
        'instructions' => 'instructions',
    ];

    /** @var array<string, string> */
    private const array CONTACT = [
        'firstName' => 'first_name',
        'lastName' => 'last_name',
        'email' => 'email',
        'phone' => 'phone',
        'mobile' => 'mobile',
    ];

    /**
     * @param  array<string, mixed>  $recipient  déjà validé par `ImportRecipientRules`
     * @return array{addressId: string, contact: array<string, mixed>}
     */
    public function create(array $recipient, string $organizationId): array
    {
        $addressAttributes = $this->pick($recipient, self::ADDRESS);
        $addressAttributes['country'] = strtoupper((string) ($addressAttributes['country'] ?? '')) ?: null;
        $addressAttributes['name'] = $this->pick($recipient, ['company' => 'name'])['name']
            ?? trim(($recipient['firstName'] ?? '').' '.($recipient['lastName'] ?? ''));

        $address = Address::create($addressAttributes);
        $contact = Contact::create($this->pick($recipient, self::CONTACT));

        EntityAddress::create([
            'organization_id' => $organizationId,
            'address_id' => $address->id,
            'entity_type' => MorphMap::ORGANIZATION,
            'entity_id' => $organizationId,
        ]);

        EntityContact::create([
            'organization_id' => $organizationId,
            'contact_id' => $contact->id,
            'entity_type' => MorphMap::ORGANIZATION,
            'entity_id' => $organizationId,
            'contact_role' => ContactRole::DELIVERY->value,
        ]);

        AddressContact::create([
            'address_id' => $address->id,
            'contact_id' => $contact->id,
            'contact_role' => ContactRole::DELIVERY->value,
            'is_primary' => true,
        ]);

        return [
            'addressId' => (string) $address->getKey(),
            'contact' => [
                'contactId' => (string) $contact->getKey(),
                'contactRole' => ContactRole::DELIVERY->value,
                'isPrimary' => true,
                'firstName' => $contact->first_name,
                'lastName' => $contact->last_name,
                'phone' => $contact->phone,
                'mobile' => $contact->mobile,
                'email' => $contact->email,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $recipient
     * @param  array<string, string>  $columns
     * @return array<string, string>
     */
    private function pick(array $recipient, array $columns): array
    {
        $attributes = [];

        foreach ($columns as $field => $column) {
            $value = $recipient[$field] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                $attributes[$column] = trim((string) $value);
            }
        }

        return $attributes;
    }
}
