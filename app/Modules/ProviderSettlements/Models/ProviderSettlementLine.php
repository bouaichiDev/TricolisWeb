<?php

declare(strict_types=1);

namespace App\Modules\ProviderSettlements\Models;

use App\Modules\Orders\Models\OrderService;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne de décompte fournisseur.
 *
 * `OrderService "1" -- "0..1" ProviderSettlementLine` : un service est décompté
 * au plus une fois. Cette unicité est **indépendante** de celle des lignes de
 * facture : le même service peut être facturé au client et décompté au
 * fournisseur — ce sont deux flux distincts (§22).
 *
 * Ni taxe, ni statut, ni date de service : le diagramme ne pose que sept
 * attributs, et le §18 interdit d'en ajouter.
 */
#[Fillable([
    'settlement_id',
    'order_service_id',
    'description',
    'quantity',
    'unit_cost',
    'total_cost',
])]
class ProviderSettlementLine extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'provider_settlement_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ProviderSettlement, $this>
     */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(ProviderSettlement::class, 'settlement_id');
    }

    /**
     * @return BelongsTo<OrderService, $this>
     */
    public function orderService(): BelongsTo
    {
        return $this->belongsTo(OrderService::class, 'order_service_id');
    }
}
