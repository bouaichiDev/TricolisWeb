<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Packages\Models\Package;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Colis servi par un service de commande
 * (`OrderService 1 — 0..* OrderServicePackage`, `Package 1 — 0..*`).
 */
#[Fillable([
    'order_service_id',
    'package_id',
    'quantity',
    'handling_instructions',
    'status',
])]
class OrderServicePackage extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = true;

    protected $table = 'order_service_packages';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<OrderService, $this>
     */
    public function orderService(): BelongsTo
    {
        return $this->belongsTo(OrderService::class, 'order_service_id');
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }
}
