<?php

declare(strict_types=1);

namespace App\Modules\Packages\Models;

use App\Modules\Organizations\Models\Organization;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Référentiel des types de colis d'une organisation (palette, carton, rolls…).
 */
#[Fillable([
    'organization_id',
    'code',
    'name',
    'status',
])]
class PackageType extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = true;

    protected $table = 'package_types';

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
     * @return HasMany<Package, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'package_type_id');
    }
}
