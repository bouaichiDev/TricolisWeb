<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use App\Modules\Pricing\Services\PricingVariableSources;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Une variable utilisable dans une formule ou une condition.
 *
 * **Le catalogue appartient à la plateforme.** Un administrateur d'organisme le
 * lit — il en a besoin pour écrire ses barèmes — mais ne l'écrit pas : sans
 * cela, chacun inventerait ses variables et une formule ne voudrait plus dire
 * la même chose d'un organisme à l'autre.
 */
#[Fillable([
    'code',
    'label',
    'description',
    'kind',
    'source_key',
    'unit',
    'position',
    'is_active',
])]
class PricingVariable extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'pricing_variables';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Les variables qu'une formule peut nommer. */
    public function scopeNumeric(Builder $query): void
    {
        $query->where('kind', PricingVariableSources::NUMERIC);
    }

    /** Celles qui filtrent une règle ou une zone, sans se multiplier. */
    public function scopeDimension(Builder $query): void
    {
        $query->where('kind', PricingVariableSources::DIMENSION);
    }

    public function scopeUsable(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position')->orderBy('code');
    }

    /**
     * D'où sort la valeur, pour l'afficher au superadmin.
     *
     * @return array{table: string, column: string, kind: string, label: string}|null
     */
    public function source(): ?array
    {
        return PricingVariableSources::all()[$this->source_key] ?? null;
    }
}
