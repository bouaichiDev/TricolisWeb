<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Support;

/**
 * Rôles livrés au portail client, et leurs permissions initiales.
 *
 * Ce ne sont que des **graines** : chaque transporteur reçoit son propre jeu,
 * qu'il pourra ajuster. Aucune règle du code ne lit ces codes pour décider
 * d'un droit — seules les permissions portées par le rôle comptent.
 *
 * Les cases « option » de la matrice ne sont pas accordées par défaut.
 */
final class CustomerPortalRoles
{
    public const string ADMIN = 'CUSTOMER_ADMIN';

    public const string ORDER_MANAGER = 'CUSTOMER_ORDER_MANAGER';

    public const string STOCK_MANAGER = 'CUSTOMER_STOCK_MANAGER';

    public const string VIEWER = 'CUSTOMER_VIEWER';

    /**
     * @return array<string, array{name: string, permissions: array<int, string>}>
     */
    public static function defaults(): array
    {
        return [
            self::ADMIN => [
                'name' => 'Administrateur client',
                'permissions' => CustomerPortalPermissions::codes(),
            ],
            self::ORDER_MANAGER => [
                'name' => 'Gestionnaire de commandes',
                'permissions' => [
                    'customer.orders.view',
                    'customer.orders.create',
                    'customer.orders.update',
                    'customer.orders.duplicate',
                    'customer.orders.cancel',
                    'customer.orders.track',
                    'customer.orders.documents.view',
                    'customer.orders.documents.upload',
                    'customer.orders.history.view',
                    'customer.orders.proofs_of_delivery.view',
                    'customer.tracking.view',
                    'customer.calendar.view',
                    'customer.catalog.view',
                    'customer.articles.view',
                    'customer.claims.view',
                    'customer.claims.create',
                    'customer.claims.update',
                    'customer.integrations.view',
                    'customer.integrations.import',
                ],
            ],
            self::STOCK_MANAGER => [
                'name' => 'Gestionnaire de stock',
                'permissions' => [
                    'customer.catalog.view',
                    'customer.articles.view',
                    'customer.articles.create',
                    'customer.articles.update',
                    'customer.stock.view',
                    'customer.stock.movements.view',
                    'customer.stock.items.create',
                    'customer.stock.items.update',
                    'customer.stock.reservations.view',
                    'customer.stock.adjustment_requests.view',
                    'customer.stock.adjustment_requests.create',
                ],
            ],
            self::VIEWER => [
                'name' => 'Lecteur',
                'permissions' => [
                    'customer.orders.view',
                    'customer.orders.track',
                    'customer.orders.documents.view',
                    'customer.orders.history.view',
                    'customer.orders.proofs_of_delivery.view',
                    'customer.tracking.view',
                    'customer.calendar.view',
                    'customer.catalog.view',
                    'customer.articles.view',
                    'customer.claims.view',
                    'customer.stock.view',
                    'customer.stock.movements.view',
                    'customer.stock.reservations.view',
                ],
            ],
        ];
    }
}
