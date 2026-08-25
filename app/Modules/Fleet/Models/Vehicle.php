<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Models;

use App\Modules\Providers\Models\Provider;
use App\Modules\Types\Models\TypeItem;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Véhicule d'un fournisseur
 * (`Provider 1 — 0..* Vehicle`, `VehicleType 1 — 0..* Vehicle`).
 *
 * Pas d'`organization_id` au diagramme : le périmètre passe par le fournisseur,
 * appliqué par le scope `inOrganization`.
 */
#[Fillable([
    'provider_id',
    'vehicle_type_id',
    'code',
    'registration_number',
    'payload_capacity',
    'volume_capacity',
    'pallet_capacity',
    'status',
])]
class Vehicle extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'vehicles';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload_capacity' => 'decimal:3',
            'volume_capacity' => 'decimal:4',
            'pallet_capacity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    /**
     * @return BelongsTo<TypeItem, $this>
     */
    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(TypeItem::class, 'vehicle_type_id');
    }

    /**
     * Restreint aux véhicules dont le fournisseur appartient à l'organisation.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->whereHas('provider', fn (Builder $provider) => $provider->where('organization_id', $organizationId));
    }
}
