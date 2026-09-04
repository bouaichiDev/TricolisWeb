<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use App\Modules\Customers\Models\Customer;
use App\Modules\Organizations\Models\Organization;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un jeu de tarifs, global ou réservé à des clients.
 *
 * La portée décide de la priorité : une liste `customer` l'emporte sur une
 * liste `global` (§169O). C'est la seule différence entre les deux — même
 * structure, mêmes règles, même moteur.
 */
#[Fillable([
    'organization_id',
    'code',
    'name',
    'scope',
    'valid_from',
    'valid_to',
    'is_active',
])]
class PriceList extends Model
{
    use HasFactory;
    use HasUlid;

    public const string GLOBAL = 'global';

    public const string CUSTOMER = 'customer';

    protected $table = 'price_lists';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(PriceRule::class);
    }

    public function matrices(): HasMany
    {
        return $this->hasMany(PriceMatrix::class);
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_price_lists')
            // Le pivot est un modele : il porte un ULID comme le reste, et
            // `attach` ne saurait pas en fabriquer un tout seul.
            ->using(CustomerPriceList::class)
            ->withTimestamps();
    }

    /**
     * Les listes utilisables aujourd'hui.
     *
     * Une période de validité échue écarte la liste sans qu'on ait à la
     * désactiver à la main — c'est ce qu'on attend d'un tarif saisonnier.
     *
     * @param  Builder<PriceList>  $query
     */
    public function scopeUsable(Builder $query, ?string $on = null): void
    {
        $day = $on ?? now()->toDateString();

        $query->where('is_active', true)
            ->where(fn (Builder $dates) => $dates->whereNull('valid_from')->orWhere('valid_from', '<=', $day))
            ->where(fn (Builder $dates) => $dates->whereNull('valid_to')->orWhere('valid_to', '>=', $day));
    }
}
