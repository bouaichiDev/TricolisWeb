<?php

declare(strict_types=1);

namespace App\Modules\Types\Models;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Packages\Models\Package;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une valeur d'un référentiel : « Camion 19T », « Palette », « Rolls ».
 *
 * Ce qui était `VehicleType`, `PackageType` et `GroupingType` vit ici, distingué
 * par sa source. Les identifiants de ces trois tables ont été repris tels
 * quels : les colis et les véhicules désignent toujours la même valeur.
 */
#[Fillable([
    'organization_id',
    'type_id',
    'code',
    'name',
    'status',
    'position',
])]
class TypeItem extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'type_items';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /**
     * @return BelongsTo<Type, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'type_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Véhicules de ce type.
     *
     * N'a de sens que pour la source `vehicle` ; ailleurs la relation est vide,
     * ce qui est exactement ce qu'il faut pour vérifier qu'une valeur n'est
     * plus utilisée avant de la supprimer.
     *
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'vehicle_type_id');
    }

    /**
     * @return HasMany<Package, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'package_type_id');
    }

    /**
     * @return HasMany<Package, $this>
     */
    public function groupedPackages(): HasMany
    {
        return $this->hasMany(Package::class, 'grouping_type_id');
    }

    /** Vrai dès qu'une ligne du schéma s'y réfère encore. */
    public function isInUse(): bool
    {
        return $this->vehicles()->exists()
            || $this->packages()->exists()
            || $this->groupedPackages()->exists();
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }
}
