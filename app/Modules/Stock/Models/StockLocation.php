<?php

declare(strict_types=1);

namespace App\Modules\Stock\Models;

use App\Modules\Agencies\Models\Depot;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Emplacement de stock, hiérarchisable
 * (`StockLocation "0..1" --> "0..*" StockLocation : parent`).
 *
 * Le périmètre passe par **deux** jointures : `depot.agency.organization_id`.
 * `depots` ne porte pas d'organisation, et le diagramme n'en met pas sur
 * l'emplacement.
 *
 * `zoneCode` reste un attribut : le §9 interdit une table `StockZone`.
 */
#[Fillable([
    'depot_id',
    'parent_location_id',
    'zone_code',
    'aisle',
    'rack',
    'level',
    'location_code',
    'barcode',
    'status',
])]
class StockLocation extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'stock_locations';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return BelongsTo<Depot, $this>
     */
    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class, 'depot_id');
    }

    /**
     * @return BelongsTo<StockLocation, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_location_id');
    }

    /**
     * @return HasMany<StockLocation, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_location_id');
    }

    /**
     * @return HasMany<StockBalance, $this>
     */
    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class, 'stock_location_id');
    }

    /**
     * @return HasMany<StockReservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class, 'stock_location_id');
    }

    /**
     * L'emplacement est-il encore occupé ?
     */
    public function isInUse(): bool
    {
        return $this->children()->exists()
            || $this->balances()->where('quantity', '>', 0)->exists()
            || $this->reservations()->whereNull('released_at')->exists();
    }

    /**
     * Restreint aux emplacements dont le dépôt relève de l'organisation.
     *
     * Deux jointures : l'emplacement tient son périmètre du dépôt, qui le tient
     * de son agence.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->whereHas('depot.agency', fn (Builder $a) => $a->where('organization_id', $organizationId));
    }
}
