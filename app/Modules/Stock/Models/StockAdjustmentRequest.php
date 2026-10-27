<?php

declare(strict_types=1);

namespace App\Modules\Stock\Models;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Stock\Enums\StockAdjustmentRequestStatus;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande de correction de stock, déposée par un client, tranchée par le
 * transporteur (`StockItem "1" -- "0..*" StockAdjustmentRequest`).
 *
 * Elle ne porte **aucune** écriture de stock : elle décrit un écart constaté.
 * L'acceptation crée le mouvement par `CreateStockMovementAction` et le
 * référence dans `stock_movement_id`.
 */
#[Fillable([
    'organization_id',
    'customer_id',
    'stock_item_id',
    'stock_location_id',
    'displayed_quantity',
    'requested_quantity',
    'reason',
    'comment',
    'status',
    'requested_by',
    'reviewed_by',
    'reviewed_at',
    'review_comment',
    'stock_movement_id',
])]
class StockAdjustmentRequest extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'stock_adjustment_requests';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'displayed_quantity' => 'decimal:3',
            'requested_quantity' => 'decimal:3',
            'status' => StockAdjustmentRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** @return BelongsTo<StockItem, $this> */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    /** @return BelongsTo<StockLocation, $this> */
    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === StockAdjustmentRequestStatus::PENDING;
    }

    /** @param  Builder<self>  $query */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }
}
