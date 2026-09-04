<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ce qui rend une règle applicable, ou non.
 *
 * Une condition ne calcule rien : elle filtre. Le §169V les veut sur le
 * service, le poids, le code postal et leurs semblables — les dimensions dont
 * on ne multiplie pas la valeur, mais qui décident quelle formule s'applique.
 */
#[Fillable([
    'price_rule_id',
    'variable',
    'operator',
    'value_from',
    'value_to',
])]
class PriceRuleCondition extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'price_rule_conditions';

    protected $keyType = 'string';

    public $incrementing = false;

    public function priceRule(): BelongsTo
    {
        return $this->belongsTo(PriceRule::class);
    }
}
