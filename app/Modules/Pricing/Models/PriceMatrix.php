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
 * Une table de décision qui choisit une règle selon une dimension.
 *
 * **Elle ne calcule pas.** Le §169W est net : la matrice sélectionne quelle
 * `PriceRule` appliquer, et c'est la formule de cette règle qui produit le
 * prix. Elle reste facultative (§169Z) — un tarif au poids n'a besoin d'aucune
 * matrice.
 */
#[Fillable([
    'price_list_id',
    'service_id',
    'code',
    'name',
    'dimension',
    'is_active',
])]
class PriceMatrix extends Model
{
    use HasFactory;
    use HasUlid;

    /** La seule dimension du besoin actuel ; d'autres se déclarent ici. */
    public const string POSTAL_CODE = 'postal_code';

    protected $table = 'price_matrices';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(PriceMatrixRow::class)->orderBy('priority')->orderBy('range_from');
    }
}
