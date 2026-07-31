<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded([])] class Order extends Model
{
    use HasUlid;

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['order_date' => 'datetime', 'source' => OrderSource::class, 'status' => OrderStatus::class, 'weight' => 'decimal:3', 'volume' => 'decimal:4'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function orderServices(): HasMany
    {
        return $this->hasMany(OrderService::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_order_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_order_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
