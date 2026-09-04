<?php

declare(strict_types=1);

namespace App\Modules\Tours\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Compteur de numéros de tournée, verrouillé pendant l'attribution.
 */
#[Fillable([
    'organization_id',
    'last_number',
])]
class TourNumberSequence extends Model
{
    use HasUlid;

    public $timestamps = true;

    public $incrementing = false;

    protected $table = 'tour_number_sequences';

    protected $keyType = 'string';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_number' => 'integer',
        ];
    }
}
