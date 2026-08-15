<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Ligne de facture.
 *
 * `OrderService "1" -- "0..1" InvoiceLine` : un service est facturé au plus une
 * fois. La contrainte unique sur `order_service_id` le garantit, et c'est elle
 * — non un statut recopié — qui dit si un service est facturé.
 *
 * `order_service_id` et `order_id` sont facultatifs : une ligne libre (frais de
 * dossier, régularisation) ne correspond à aucune prestation.
 *
 * `orderId` est un raccourci de lecture vers la commande : redondant avec
 * `orderService.order_id` quand le service est renseigné, seul utile sinon.
 */
#[Fillable([
    'invoice_id',
    'order_service_id',
    'order_id',
    'line_number',
    'service_code',
    'description',
    'customer_order_reference',
    'quantity',
    'unit_price',
    'discount_rate',
    'tax_rate',
    'total_excluding_tax',
    'total_including_tax',
    'service_completed_at',
    'status',
])]
class InvoiceLine extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'invoice_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount_rate' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'total_excluding_tax' => 'decimal:2',
            'total_including_tax' => 'decimal:2',
            'service_completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * @return BelongsTo<OrderService, $this>
     */
    public function orderService(): BelongsTo
    {
        return $this->belongsTo(OrderService::class, 'order_service_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * @return HasOne<InvoiceLineAddressSnapshot, $this>
     */
    public function addressSnapshot(): HasOne
    {
        return $this->hasOne(InvoiceLineAddressSnapshot::class, 'invoice_line_id');
    }
}
