<?php

declare(strict_types=1);

namespace App\Modules\Tours\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Période d'une tournée (`Tour "1" *-- "0..*" TourPeriod`).
 *
 * L'arrêt est facultatif : `TourStop "0..1" -- "0..*" TourPeriod`. Une période
 * de conduite entre deux arrêts n'appartient à aucun arrêt.
 *
 * `periodType` et `status` sont des chaînes libres — le diagramme n'en énumère
 * aucune valeur.
 */
#[Fillable([
    'tour_id',
    'tour_stop_id',
    'period_type',
    'sequence',
    'planned_start_at',
    'planned_end_at',
    'actual_start_at',
    'actual_end_at',
    'break_minutes',
    'service_minutes',
    'waiting_minutes',
    'travel_minutes',
    'distance_meters',
    'internal_remark',
    'status',
])]
class TourPeriod extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'tour_periods';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'planned_start_at' => 'datetime',
            'planned_end_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
            'break_minutes' => 'integer',
            'service_minutes' => 'integer',
            'waiting_minutes' => 'integer',
            'travel_minutes' => 'integer',
            'distance_meters' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Tour, $this>
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class, 'tour_id');
    }

    /**
     * @return BelongsTo<TourStop, $this>
     */
    public function tourStop(): BelongsTo
    {
        return $this->belongsTo(TourStop::class, 'tour_stop_id');
    }

    /**
     * @return HasMany<TourPeriodAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TourPeriodAssignment::class, 'tour_period_id');
    }
}
