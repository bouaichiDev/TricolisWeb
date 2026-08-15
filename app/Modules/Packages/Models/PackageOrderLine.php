<?php

declare(strict_types=1);

namespace App\Modules\Packages\Models;

use App\Modules\Orders\Models\OrderLine;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Répartition d'une ligne de commande dans un colis.
 *
 * Une ligne peut être éclatée entre plusieurs colis ; la somme des quantités
 * affectées ne peut pas dépasser la quantité commandée.
 */
#[Fillable([
    'package_id',
    'order_line_id',
    'quantity',
])]
class PackageOrderLine extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = true;

    protected $table = 'package_order_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    /**
     * @return BelongsTo<OrderLine, $this>
     */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class, 'order_line_id');
    }
}
