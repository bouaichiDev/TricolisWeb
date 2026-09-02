<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Contacts\Models\AddressContact;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\EntityContact;
use App\Modules\Customers\Models\Customer;
use App\Modules\Organizations\Models\Organization;

/**
 * Cinq clients, chacun avec trois adresses et leurs contacts.
 *
 * **Une adresse par commande était le défaut du semis précédent.** Il en créait
 * une neuve à chaque commande et la rattachait au client : trente commandes
 * donnaient trente rues différentes dans la même ville, et l'onglet « Adresses »
 * d'un client devenait illisible. Un vrai client livre sur quelques lieux qu'il
 * réutilise — c'est ce que ce carnet reproduit.
 *
 * Les trois rôles couvrent ce qu'une commande met en jeu : où l'on charge, où
 * l'on livre, où part la facture. Chacun porte son contact, parce que la
 * personne à prévenir dépend du lieu.
 *
 * **Rejouable.** Deux semis appellent ce carnet ; le second doit retrouver les
 * adresses du premier, sans quoi chaque client en aurait six.
 */
final readonly class SwissCustomerBook
{
    public const int CUSTOMERS = 5;

    /** Les rôles d'adresse, dans l'ordre où le carnet les crée. */
    public const array ROLES = ['delivery', 'load', 'billing'];

    /** @var list<string> */
    private const array FIRST_NAMES = ['Anne', 'Marc', 'Sofia', 'Luca', 'Nadia'];

    /** @var list<string> */
    private const array LAST_NAMES = ['Rochat', 'Meier', 'Fontana', 'Blanc', 'Dubois'];

    /** @var array<string, string> */
    private const array ROLE_LABELS = [
        'delivery' => 'Livraison',
        'load' => 'Chargement',
        'billing' => 'Facturation',
    ];

    /** @return list<SeededCustomer> */
    public function forOrganization(Organization $organization): array
    {
        $customers = [];

        for ($index = 0; $index < self::CUSTOMERS; $index++) {
            $customers[] = $this->one($organization, $index);
        }

        return $customers;
    }

    private function one(Organization $organization, int $index): SeededCustomer
    {
        $city = SwissAddressBook::at($index)['city'];

        $customer = Customer::firstOrCreate(
            ['organization_id' => $organization->id, 'code' => sprintf('CH-%02d', $index + 1)],
            [
                'name' => sprintf('Client %s', $city),
                'email' => sprintf('contact%d@example.ch', $index + 1),
                'status' => 'active',
            ],
        );

        $addressIds = [];
        $contacts = [];

        foreach (self::ROLES as $slot => $role) {
            $address = $this->address($organization, $customer, $index, $slot, $role);
            $contact = $this->contact($organization, $customer, $address, $index, $slot, $role);

            $addressIds[$role] = $address->id;
            $contacts[$role] = $contact;
        }

        return new SeededCustomer($customer->id, $customer->code, $addressIds, $contacts);
    }

    /**
     * L'adresse de ce rôle, créée si le client n'en a pas.
     *
     * C'est la liaison `EntityAddress` qui la rend visible : une adresse sans
     * elle n'apparaît dans aucune liste de l'organisation. C'est aussi elle qui
     * sert de clé — un client ne porte qu'une adresse par rôle, et la retrouver
     * évite d'en empiler à chaque semis.
     *
     * Le décalage de rôle change de rue tout en gardant la ville : les trois
     * adresses d'un client restent chez lui, à quelques centaines de mètres.
     */
    private function address(
        Organization $organization,
        Customer $customer,
        int $index,
        int $slot,
        string $role,
    ): Address {
        $link = EntityAddress::where('organization_id', $organization->id)
            ->where('entity_type', 'customer')
            ->where('entity_id', $customer->id)
            ->where('address_type', $role)
            ->first();

        if ($link !== null) {
            return Address::findOrFail($link->address_id);
        }

        $attributes = SwissAddressBook::at($index + $slot * SwissAddressBook::localityCount());
        $attributes['name'] = sprintf('%s — %s', $customer->name, self::ROLE_LABELS[$role]);

        $address = Address::create($attributes);

        EntityAddress::create([
            'organization_id' => $organization->id,
            'address_id' => $address->id,
            'entity_type' => 'customer',
            'entity_id' => $customer->id,
            'address_type' => $role,
            // La livraison fait défaut : c'est elle qu'un écran propose en
            // premier quand il n'a rien d'autre pour choisir.
            'is_default' => $role === 'delivery',
        ]);

        return $address;
    }

    /**
     * Le contact de cette adresse, rattaché aussi au client.
     *
     * Les deux liaisons ne font pas doublon : `AddressContact` répond à « qui
     * prévenir en arrivant ici », `EntityContact` à « qui connaît-on chez ce
     * client ». Le second onglet serait vide sans elle.
     */
    private function contact(
        Organization $organization,
        Customer $customer,
        Address $address,
        int $index,
        int $slot,
        string $role,
    ): Contact {
        $existing = AddressContact::where('address_id', $address->id)->first();

        if ($existing !== null) {
            return Contact::findOrFail($existing->contact_id);
        }

        $position = $index * count(self::ROLES) + $slot;
        $first = self::FIRST_NAMES[$position % count(self::FIRST_NAMES)];
        $last = self::LAST_NAMES[intdiv($position, count(self::FIRST_NAMES)) % count(self::LAST_NAMES)];

        $contact = Contact::create([
            'first_name' => $first,
            'last_name' => $last,
            'phone' => sprintf('+41 21 %03d %02d %02d', 100 + $position, $position % 90, ($position * 3) % 90),
            'email' => sprintf('%s.%s@%s.example.ch', mb_strtolower($first), mb_strtolower($last), $role),
            'preferred_language' => 'fr',
            'is_active' => true,
        ]);

        AddressContact::create([
            'address_id' => $address->id,
            'contact_id' => $contact->id,
            'contact_role' => $role,
            'is_primary' => true,
        ]);

        EntityContact::create([
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'entity_type' => 'customer',
            'entity_id' => $customer->id,
            'contact_role' => $role,
            'is_primary' => $role === 'delivery',
        ]);

        return $contact;
    }
}
