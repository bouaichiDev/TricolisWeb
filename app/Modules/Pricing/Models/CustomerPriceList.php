<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Le rattachement d'une liste de prix à un client.
 *
 * Une entité à part entière, et non une simple table de liaison : le diagramme
 * la nomme, et elle porte un identifiant comme le reste du modèle. C'est aussi
 * ce qui permet à une même liste négociée de servir plusieurs enseignes d'un
 * même groupe sans recopier ses règles.
 */
class CustomerPriceList extends Pivot
{
    use HasUlid;

    protected $table = 'customer_price_lists';

    protected $keyType = 'string';

    public $incrementing = false;
}
