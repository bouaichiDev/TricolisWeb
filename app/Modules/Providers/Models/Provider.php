<?php

declare(strict_types=1);

namespace App\Modules\Providers\Models;

use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Organizations\Models\Organization;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fournisseur de transport (`Organization 1 — 0..* Provider`).
 *
 * Ni adresse ni contact : le diagramme n'en définit pas. Une liaison passerait
 * par les mécanismes partagés `EntityAddress` / `EntityContact`.
 *
 * `legacy_id` sert la reprise depuis l'ancienne plateforme ; il est masqué par
 * défaut pour ne pas fuiter dans les réponses.
 */
#[Fillable([
    'legacy_id',
    'organization_id',
    'code',
    'name',
    'provider_type',
    'status',
])]
#[Hidden(['legacy_id'])]
class Provider extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'providers';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
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
     * @return HasMany<Driver, $this>
     */
    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class, 'provider_id');
    }

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'provider_id');
    }

    /**
     * Le fournisseur est-il encore rattaché à des ressources ?
     *
     * Sert le refus de suppression : un fournisseur qui a des chauffeurs ou des
     * véhicules ne se supprime pas sans les traiter d'abord.
     */
    public function hasResources(): bool
    {
        return $this->drivers()->exists() || $this->vehicles()->exists();
    }
}
