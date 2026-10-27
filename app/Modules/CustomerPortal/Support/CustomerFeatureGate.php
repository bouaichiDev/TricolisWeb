<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Support;

use App\Modules\Customers\Models\Customer;

/**
 * Ce que le transporteur a ouvert à ce client.
 *
 * La fiche `Customer` porte déjà des interrupteurs (`stock_enabled`,
 * `catalog_enabled`, `tracking_enabled`), réglés depuis l'interne. Un rôle du
 * portail ne peut pas les franchir : une permission `customer.stock.view` ne
 * vaut rien pour un client dont le stock n'est pas géré.
 *
 * Les modules sans interrupteur ne sont pas bridés ici.
 */
final class CustomerFeatureGate
{
    /** @var array<string, string> préfixe de permission => attribut du Customer */
    private const array FEATURES = [
        'customer.stock.' => 'stock_enabled',
        'customer.catalog.' => 'catalog_enabled',
        'customer.articles.' => 'catalog_enabled',
        'customer.tracking.' => 'tracking_enabled',
        'customer.orders.track' => 'tracking_enabled',
    ];

    public static function allows(Customer $customer, string $permission): bool
    {
        foreach (self::FEATURES as $prefix => $attribute) {
            if (str_starts_with($permission, $prefix)) {
                return (bool) $customer->getAttribute($attribute);
            }
        }

        return true;
    }

    /**
     * @return array<string, bool>
     */
    public static function featuresOf(Customer $customer): array
    {
        return [
            'catalog' => (bool) $customer->catalog_enabled,
            'stock' => (bool) $customer->stock_enabled,
            'tracking' => (bool) $customer->tracking_enabled,
            'packages' => (bool) $customer->package_enabled,
            'appointments' => (bool) $customer->appointment_enabled,
        ];
    }
}
