<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Organizations\Models\Organization;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded([])]
class Service extends Model
{
    use HasUlid;

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['billable_to_customer' => 'boolean', 'payable_to_provider' => 'boolean', 'requires_address' => 'boolean', 'requires_contact' => 'boolean'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function orderServices(): HasMany
    {
        return $this->hasMany(OrderService::class);
    }
}
