<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\EntityContact;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Packages\Models\Package;

/**
 * Ce qu'une commande de démonstration porte : son adresse, son contact, ses
 * articles, ses colis.
 *
 * Séparé de la fabrique pour la garder lisible : celle-ci décide de la forme
 * d'une commande, celui-ci écrit ses pièces.
 */
final readonly class SwissOrderParts
{
    public const int PACKAGES = 2;

    public const float PACKAGE_WEIGHT = 12.5;

    public const float PACKAGE_VOLUME = 0.08;

    /** @var list<string> */
    private const array FIRST_NAMES = ['Anne', 'Marc', 'Sofia', 'Luca', 'Nadia', 'Yves', 'Elena', 'Pierre'];

    /** @var list<string> */
    private const array LAST_NAMES = ['Rochat', 'Meier', 'Fontana', 'Blanc', 'Dubois', 'Keller', 'Rossi', 'Girard'];

    public function __construct(private string $organizationId) {}

    /**
     * L'adresse de livraison, rattachée au client.
     *
     * C'est la liaison qui la rend visible : une adresse sans `EntityAddress`
     * n'apparaît dans aucune liste de l'organisation.
     */
    public function address(int $index, string $customerId): Address
    {
        $address = Address::create(SwissAddressBook::at($index));

        EntityAddress::create([
            'organization_id' => $this->organizationId,
            'address_id' => $address->id,
            'entity_type' => 'customer',
            'entity_id' => $customerId,
            'address_type' => 'delivery',
            'is_default' => false,
        ]);

        return $address;
    }

    public function contact(int $index, string $customerId): Contact
    {
        $first = self::FIRST_NAMES[$index % count(self::FIRST_NAMES)];
        $last = self::LAST_NAMES[$index % count(self::LAST_NAMES)];

        $contact = Contact::create([
            'first_name' => $first,
            'last_name' => $last,
            'phone' => sprintf('+41 21 %03d %02d %02d', 100 + $index % 800, $index % 90, ($index * 3) % 90),
            'email' => sprintf('%s.%s%d@example.ch', mb_strtolower($first), mb_strtolower($last), $index),
            'preferred_language' => 'fr',
            'is_active' => true,
        ]);

        EntityContact::create([
            'organization_id' => $this->organizationId,
            'contact_id' => $contact->id,
            'entity_type' => 'customer',
            'entity_id' => $customerId,
            'contact_role' => 'delivery',
            'is_primary' => false,
        ]);

        return $contact;
    }

    /** @return list<Package> */
    public function packages(Order $order): array
    {
        $packages = [];

        for ($position = 1; $position <= self::PACKAGES; $position++) {
            $packages[] = Package::create([
                'order_id' => $order->id,
                'reference' => sprintf('%s-C%d', $order->order_number, $position),
                'description' => 'Carton standard',
                'quantity' => 1,
                'weight' => self::PACKAGE_WEIGHT,
                'volume' => self::PACKAGE_VOLUME,
                'status' => 'ready',
            ]);
        }

        return $packages;
    }

    public function lines(Order $order): void
    {
        $articles = [
            ['ART-CH-001', 'Meuble en kit', 2],
            ['ART-CH-002', 'Accessoires de montage', 4],
        ];

        foreach ($articles as $position => [$code, $name, $quantity]) {
            OrderLine::create([
                'order_id' => $order->id,
                'article_code' => $code,
                'name' => $name,
                'quantity' => $quantity,
                'weight' => self::PACKAGE_WEIGHT / 2,
                'volume' => self::PACKAGE_VOLUME / 2,
                'selling_price' => 49.90 + $position * 10,
                'status' => 'active',
            ]);
        }
    }
}
