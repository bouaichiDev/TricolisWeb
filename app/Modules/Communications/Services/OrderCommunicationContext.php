<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services;

use App\Modules\Orders\Models\Order;

/**
 * Ce qu'une communication automatique a le droit de savoir d'une commande.
 *
 * **Une liste close, écrite à la main.** Le §20 interdit l'accès arbitraire aux
 * modèles : un template pouvant écrire `{{ customer_api_key }}` ferait fuiter
 * par un SMS ce que l'API protège. Rien n'est lu par réflexion ici — chaque clé
 * est nommée, une par une.
 *
 * Les mêmes valeurs servent deux usages, et c'est voulu :
 *
 * - **le rendu** — ce que le template substitue, filtré sur ce qu'il déclare ;
 * - **les conditions** — les faits que l'évaluateur compare.
 *
 * Deux jeux distincts auraient laissé configurer une condition sur un champ que
 * le template ne peut pas nommer, sans que rien ne le signale.
 *
 * Les noms sont plats et en minuscules : c'est ce que le motif des conditions
 * accepte (`/^[a-z][a-z0-9_]{0,63}$/`), et un chemin pointé y serait refusé.
 */
final readonly class OrderCommunicationContext
{
    /**
     * @return array<string, scalar|null>
     */
    public function build(Order $order): array
    {
        $order->loadMissing(['customer', 'agency']);

        $customer = $order->customer;

        return [
            'order_number' => $order->order_number,
            'order_status' => $order->status?->value,
            'order_type' => $order->order_type,
            'order_source' => $order->source?->value,
            'order_date' => $order->order_date?->toDateString(),
            'external_reference' => $order->external_reference,
            'customer_reference' => $order->customer_reference,
            'group_code' => $order->group_code,
            'currency_code' => $order->currency_code,
            'weight' => $order->weight === null ? null : (string) $order->weight,
            'volume' => $order->volume === null ? null : (string) $order->volume,
            'package_count' => $order->package_count,
            'customer_code' => $customer?->code,
            'customer_name' => $customer?->name,
            'agency_name' => $order->agency?->name,
        ];
    }

    /**
     * Les seules valeurs que ce template déclare savoir recevoir.
     *
     * Le moteur refuse une valeur non déclarée : lui passer le contexte entier
     * ferait échouer tout template n'en nommant qu'une partie — c'est-à-dire
     * tous. L'inverse — une variable déclarée que le contexte ne fournit pas —
     * échoue, et c'est juste : le template demande ce que le système ne sait
     * pas dire.
     *
     * @param  array<string, scalar|null>  $context
     * @param  list<string>  $declared
     * @return array<string, scalar|null>
     */
    public function forTemplate(array $context, array $declared): array
    {
        return array_intersect_key($context, array_flip($declared));
    }
}
