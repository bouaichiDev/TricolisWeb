<?php

declare(strict_types=1);

namespace App\Modules\Stock\Models;

use App\Modules\Orders\Models\OrderLine;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réservation de stock pour une ligne de commande.
 *
 * Une réservation libérée **n'est pas supprimée** : `released_at` est renseigné
 * et la ligne reste. Le §23 l'exige, et c'est ce qui permet de retracer ce qui
 * a été immobilisé puis relâché.
 */
#[Fillable([
    'stock_item_id',
    'stock_location_id',
    'order_line_id',
    'quantity',
    'status',
    'reserved_at',
    'released_at',
])]
class StockReservation extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'stock_reservations';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'reserved_at' => 'datetime',
            'released_at' => 'datetime',
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
     * @return BelongsTo<OrderLine, $this>
     */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class, 'order_line_id');
    }

    /**
     * Une réservation libérée ne se libère pas deux fois — sinon la quantité
     * réservée serait décrémentée autant de fois qu'on appelle la route.
     */
    public function isReleased(): bool
    {
        return $this->released_at !== null;
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
