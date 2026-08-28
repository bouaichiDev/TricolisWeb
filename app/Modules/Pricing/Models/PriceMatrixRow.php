<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une zone de la matrice, et la règle qu'elle désigne.
 *
 * **Un code postal n'est pas partout un entier.** Le §169AB le rappelle :
 * `01234` perd son zéro de tête dès qu'on le convertit, et certains pays y
 * mettent des lettres. `match_mode` dit donc comment lire les bornes —
 * `numeric` pour des plages comme `1144 → 4000`, `prefix` pour un début de
 * code, `exact` pour une valeur unique.
 */
#[Fillable([
    'price_matrix_id',
    'price_rule_id',
    'label',
    'match_mode',
    'range_from',
    'range_to',
    'priority',
])]
class PriceMatrixRow extends Model
{
    use HasFactory;
    use HasUlid;

    public const string NUMERIC = 'numeric';

    public const string PREFIX = 'prefix';

    public const string EXACT = 'exact';

    protected $table = 'price_matrix_rows';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['priority' => 'integer'];
    }

    public function matrix(): BelongsTo
    {
        return $this->belongsTo(PriceMatrix::class, 'price_matrix_id');
    }

    public function priceRule(): BelongsTo
    {
        return $this->belongsTo(PriceRule::class);
    }

    /**
     * Cette zone couvre-t-elle la valeur lue sur la prestation ?
     *
     * En mode numérique, une borne haute absente vaut « et au-delà » : c'est la
     * dernière zone d'un barème, celle qu'on oublie sinon de fermer.
     */
    public function covers(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return match ($this->match_mode) {
            self::PREFIX => str_starts_with($value, $this->range_from),
            self::EXACT => $value === $this->range_from,
            default => $this->coversNumerically($value),
        };
    }

    private function coversNumerically(string $value): bool
    {
        if (! is_numeric($value) || ! is_numeric($this->range_from)) {
            return false;
        }

        $number = (float) $value;

        if ($number < (float) $this->range_from) {
            return false;
        }

        return $this->range_to === null || ! is_numeric($this->range_to)
            ? true
            : $number <= (float) $this->range_to;
    }
}
