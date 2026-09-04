<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\OrderService;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ce qui explique un prix, après coup.
 *
 * **La formule y est recopiée, pas référencée.** Le §169N l'exige : si la
 * formule change demain, la facture d'hier doit continuer à s'expliquer par
 * celle qui l'a produite. Les références vers les règles servent à retrouver
 * l'origine tant qu'elle existe ; le texte de la formule et les variables
 * survivent à sa suppression.
 */
#[Fillable([
    'organization_id',
    'order_service_id',
    'customer_id',
    'price_list_id',
    'price_rule_id',
    'price_matrix_id',
    'price_matrix_row_id',
    'scope',
    'service_code',
    'formula_snapshot',
    'variables_snapshot',
    'result',
    'currency_code',
    'calculated_at',
])]
class PricingCalculation extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'pricing_calculations';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variables_snapshot' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function orderService(): BelongsTo
    {
        return $this->belongsTo(OrderService::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function priceRule(): BelongsTo
    {
        return $this->belongsTo(PriceRule::class);
    }
}
