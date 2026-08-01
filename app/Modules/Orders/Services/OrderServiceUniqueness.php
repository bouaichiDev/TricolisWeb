<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Models\Order;
use Illuminate\Validation\Rule;

/**
 * Unicité du numéro et de la séquence d'un service dans sa commande.
 *
 * Deux services partageant une séquence rendraient l'ordre de passage
 * indéterminé ; deux services partageant un numéro rendraient la facturation
 * ambiguë. Les deux contraintes existent en base, cette classe produit le
 * message de validation avant que MySQL ne renvoie une erreur brute.
 */
final readonly class OrderServiceUniqueness
{
    public function assert(Order $order, ?string $serviceNumber, ?int $sequence, ?string $ignoreId = null): void
    {
        $values = [];
        $rules = [];

        if ($serviceNumber !== null) {
            $values['serviceNumber'] = $serviceNumber;
            $rules['serviceNumber'] = [
                Rule::unique('order_services', 'service_number')->where('order_id', $order->id)->ignore($ignoreId),
            ];
        }

        if ($sequence !== null) {
            $values['sequence'] = $sequence;
            $rules['sequence'] = [
                Rule::unique('order_services', 'sequence')->where('order_id', $order->id)->ignore($ignoreId),
            ];
        }

        if ($rules !== []) {
            validator($values, $rules)->validate();
        }
    }
}
