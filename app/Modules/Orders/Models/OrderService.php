<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Addresses\Models\Address;
use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded([])]
class OrderService extends Model
{
    use HasUlid;

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['requested_date' => 'date', 'requested_from' => 'datetime', 'requested_to' => 'datetime', 'status' => OrderServiceStatus::class];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }
}
