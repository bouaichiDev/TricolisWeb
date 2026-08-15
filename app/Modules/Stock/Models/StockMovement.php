<?php

declare(strict_types=1);

namespace App\Modules\Stock\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mouvement de stock — donnée historique.
 *
 * Un mouvement ne se modifie pas : aucune route `PATCH` ni `DELETE`, aucun
 * `updated_at`. Une correction est un nouveau mouvement.
 *
 * `sourceEntityType` porte un **alias de la morph map**, jamais un nom de
 * classe PHP. Aucune relation Eloquent polymorphe n'est déclarée : la colonne
 * n'a pas de clé étrangère et peut désigner plusieurs tables — en faire un
 * `morphTo` donnerait l'illusion d'une intégrité qui n'existe pas.
 */
#[Fillable([
    'stock_item_id',
    'source_location_id',
    'destination_location_id',
    'movement_type',
    'quantity',
    'source_entity_type',
    'source_entity_id',
    'created_by',
    'created_at',
])]
class StockMovement extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'stock_movements';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'created_at' => 'datetime',
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
    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'source_location_id');
    }

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'destination_location_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
