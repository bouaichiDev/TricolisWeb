<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Compteur de numéros de commande, verrouillé pendant l'attribution.
 */
#[Fillable([
    'organization_id',
    'scope',
    'year',
    'last_number',
])]
class OrderNumberSequence extends Model
{
    use HasUlid;

    public $timestamps = true;

    protected $table = 'order_number_sequences';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
