<?php

declare(strict_types=1);

namespace App\Modules\Types\Models;

use App\Modules\Organizations\Models\Organization;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une source de valeurs : véhicule, colis, groupage — et ce que l'organisme
 * décide d'y ajouter (`Organization 1 — 0..* Type`, `Type 1 — 0..* TypeItem`).
 *
 * Les sources marquées `is_system` sont celles auxquelles une colonne se
 * réfère : `vehicles.vehicle_type_id`, `packages.package_type_id`,
 * `packages.grouping_type_id`. Elles se renomment mais ne se suppriment pas —
 * leur disparition laisserait ces colonnes sans cible.
 */
#[Fillable([
    'organization_id',
    'code',
    'name',
    'status',
])]
class Type extends Model
{
    use HasFactory;
    use HasUlid;

    /** Sources auxquelles une colonne du schéma se réfère. */
    public const array SYSTEM_CODES = ['package', 'grouping', 'vehicle'];

    protected $table = 'types';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return HasMany<TypeItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TypeItem::class, 'type_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }
}
