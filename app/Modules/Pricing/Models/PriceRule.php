<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use App\Modules\Orders\Models\Service;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une règle de calcul : une formule, et les conditions qui la rendent applicable.
 *
 * `service_id` nul veut dire « toute prestation que mes conditions acceptent ».
 * Le §169O fait passer une règle qui nomme le service avant une règle générique :
 * la plus précise gagne, faute de quoi une formule attrape-tout masquerait les
 * tarifs réels.
 */
#[Fillable([
    'price_list_id',
    'service_id',
    'code',
    'name',
    'formula',
    'priority',
    'is_active',
])]
class PriceRule extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'price_rules';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(PriceRuleCondition::class);
    }

    /**
     * Les zones de matrice qui désignent cette règle.
     *
     * Leur présence change son statut : une règle citée par une matrice ne
     * s'applique que par elle, sinon les bornes de zones ne voudraient rien
     * dire.
     */
    public function matrixRows(): HasMany
    {
        return $this->hasMany(PriceMatrixRow::class);
    }
}
