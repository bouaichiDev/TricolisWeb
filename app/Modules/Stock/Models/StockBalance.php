<?php

declare(strict_types=1);

namespace App\Modules\Stock\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solde d'un article à un emplacement.
 *
 * Un seul solde par couple article + emplacement : c'est cette unicité qui rend
 * le verrouillage pessimiste possible et suffisant.
 *
 * `available_quantity` est stockée — le diagramme la déclare — mais toujours
 * dérivée : `quantity − reserved_quantity`. Elle n'est jamais acceptée en
 * entrée.
 *
 * Seule classe de la phase à porter `updatedAt` : un solde est un état courant,
 * pas un événement. `$timestamps = false` parce que `created_at` n'existe pas ;
 * la date est posée par les Actions.
 */
#[Fillable([
    'stock_item_id',
    'stock_location_id',
    'quantity',
    'reserved_quantity',
    'available_quantity',
    'updated_at',
])]
class StockBalance extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'stock_balances';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
            'available_quantity' => 'decimal:3',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StockItem, $this>
     */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->whereHas(
            'stockItem.customer',
            fn (Builder $c) => $c->where('organization_id', $organizationId),
        );
    }
}
