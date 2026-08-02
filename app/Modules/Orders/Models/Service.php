<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Organizations\Models\Organization;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Référentiel des prestations facturables : livraison, enlèvement, montage,
 * reprise, deuxième passage…
 */
#[Fillable([
    'organization_id',
    'code',
    'name',
    'unit',
    'default_duration_minutes',
    'billable_to_customer',
    'payable_to_provider',
    'requires_address',
    'requires_contact',
    'status',
])]
class Service extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billable_to_customer' => 'boolean',
            'payable_to_provider' => 'boolean',
            'requires_address' => 'boolean',
            'requires_contact' => 'boolean',
            'default_duration_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<OrderService, $this>
     */
    public function orderServices(): HasMany
    {
        return $this->hasMany(OrderService::class);
    }
}
