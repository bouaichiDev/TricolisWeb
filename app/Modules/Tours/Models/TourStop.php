<?php

declare(strict_types=1);

namespace App\Modules\Tours\Models;

use App\Modules\Addresses\Models\Address;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Arrêt d'une tournée (`Tour "1" *-- "0..*" TourStop`).
 *
 * Composition : un arrêt n'existe pas hors de sa tournée, et son périmètre
 * organisationnel est celui de cette tournée.
 *
 * Le diagramme impose au moins un `TourStopService` par arrêt (`1..*`) : la
 * création est atomique, et le dernier service actif ne peut pas être retiré.
 */
#[Fillable([
    'tour_id',
    'address_id',
    'sequence',
    'grouping_key',
    'generation_mode',
    'planned_arrival_at',
    'planned_departure_at',
    'actual_arrival_at',
    'actual_departure_at',
    'waiting_minutes',
    'service_minutes',
    'status',
])]
class TourStop extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'tour_stops';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'planned_arrival_at' => 'datetime',
            'planned_departure_at' => 'datetime',
            'actual_arrival_at' => 'datetime',
            'actual_departure_at' => 'datetime',
            'waiting_minutes' => 'integer',
            'service_minutes' => 'integer',
            'status' => TourStopStatus::class,
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
     * @return BelongsTo<Address, $this>
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'address_id');
    }

    /**
     * @return HasMany<TourStopService, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(TourStopService::class, 'tour_stop_id');
    }

    /**
     * @return HasMany<TourPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(TourPeriod::class, 'tour_stop_id');
    }

    /**
     * Nombre de services encore actifs sur cet arrêt.
     *
     * Sert la cardinalité `1..*` : retirer le dernier laisserait un arrêt que
     * le diagramme n'autorise pas.
     */
    public function activeServiceCount(): int
    {
        return $this->services()->where('is_active_assignment', true)->count();
    }
}
