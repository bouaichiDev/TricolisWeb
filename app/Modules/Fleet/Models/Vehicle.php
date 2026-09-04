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
 * Véhicule d'une organisation, éventuellement fourni par un tiers
 * (`Provider 0..1 — 0..* Vehicle`, `TypeItem 1 — 0..* Vehicle`).
 *
 * **Le fournisseur est facultatif** : un transporteur possède ses propres
 * camions. L'organisation est donc portée en propre — la déduire du fournisseur
 * laisserait sans périmètre un véhicule qui n'en a pas.
 */
#[Fillable([
    'organization_id',
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
        // La colonne porte desormais l'organisation : un vehicule du
        // transporteur n'a pas de fournisseur par lequel la deduire.
        $query->where('organization_id', $organizationId);
    }
}
