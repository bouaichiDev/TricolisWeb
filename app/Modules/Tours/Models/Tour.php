<?php

declare(strict_types=1);

namespace App\Modules\Tours\Models;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Providers\Models\Provider;
use App\Modules\Tours\Enums\TourStatus;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tournée — racine de l'agrégat de planification.
 *
 * Seule classe de la phase à porter `organization_id` : les arrêts, services,
 * périodes et affectations tiennent leur périmètre d'elle.
 *
 * Dépôt, fournisseur, chauffeur et véhicule sont facultatifs — le diagramme les
 * pose en `0..1`, et une tournée se planifie avant d'être affectée.
 */
#[Fillable([
    'organization_id',
    'tour_number',
    'tour_date',
    'agency_id',
    'depot_id',
    'provider_id',
    'vehicle_id',
    'driver_id',
    'telematics_reference',
    'tour_type',
    'instructions',
    'planned_start_at',
    'planned_end_at',
    'actual_start_at',
    'actual_end_at',
    'total_weight',
    'total_volume',
    'total_packages',
    'total_customers',
    'driving_time_minutes',
    'working_time_minutes',
    'distance_meters',
    'status',
])]
class Tour extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'tours';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tour_date' => 'date',
            'planned_start_at' => 'datetime',
            'planned_end_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
            'total_weight' => 'decimal:3',
            'total_volume' => 'decimal:4',
            'total_packages' => 'integer',
            'total_customers' => 'integer',
            'driving_time_minutes' => 'integer',
            'working_time_minutes' => 'integer',
            'distance_meters' => 'integer',
            'status' => TourStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    /**
     * @return BelongsTo<Depot, $this>
     */
    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class, 'depot_id');
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    /**
     * @return HasMany<TourStop, $this>
     */
    public function stops(): HasMany
    {
        return $this->hasMany(TourStop::class, 'tour_id');
    }

    /**
     * @return HasMany<TourPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(TourPeriod::class, 'tour_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }
}
