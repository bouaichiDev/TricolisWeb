<?php

declare(strict_types=1);

namespace App\Modules\Agencies\Models;

use App\Modules\Orders\Models\Order;
use App\Modules\Organizations\Models\Organization;
use App\Shared\Database\Concerns\HasUlid;
use Database\Factories\Modules\Agencies\Models\AgencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'code',
    'name',
    'short_name',
    'email',
    'phone',
    'color',
    'loading_point',
    'status',
])]
class Agency extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = true;

    protected $table = 'agencies';

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
     * @return HasMany<Depot, $this>
     */
    public function depots(): HasMany
    {
        return $this->hasMany(Depot::class, 'agency_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'agency_id');
    }

    protected static function newFactory(): AgencyFactory
    {
        return AgencyFactory::new();
    }
}
