<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\DTOs\CreateOrderLineData;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Orders\Services\OrderScopeGuard;

/**
 * Crée les lignes d'une commande.
 *
 * Une ligne issue du catalogue recopie les données de l'article : la commande
 * devient autonome et une modification ultérieure du catalogue ne réécrit pas
 * l'historique. Les valeurs fournies dans le payload restent prioritaires sur
 * celles de l'article, pour permettre un ajustement ponctuel.
 */
final readonly class CreateOrderLines
{
    public function __construct(private OrderScopeGuard $guard) {}

    /**
     * @param  list<CreateOrderLineData>  $lines
     * @return array<string, OrderLine> indexé par clé locale puis par identifiant
     */
    public function execute(Order $order, Customer $customer, array $lines): array
    {
        $created = [];

        foreach ($lines as $index => $line) {
            $attributes = $line->attributes;

            if ($line->comesFromCatalog()) {
                $item = $this->guard->catalogItem($line->catalogItemId, $customer, "lines.$index.catalogItemId");
                $attributes = array_merge($item->toOrderLineSnapshot(), $attributes);
            }

            $model = $order->lines()->create($attributes);
            $created[(string) $index] = $model;
            $created[$model->id] = $model;
        }

        return $created;
    }
}
