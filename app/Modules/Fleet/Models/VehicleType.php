<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Models;

use App\Modules\Organizations\Models\Organization;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Référentiel des types de véhicule (`Organization 1 — 0..* VehicleType`).
 */
#[Fillable([
    'organization_id',
    'code',
    'name',
    'status',
])]
class VehicleType extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'vehicle_types';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'vehicle_type_id');
    }
}
