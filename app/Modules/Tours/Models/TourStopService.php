<?php

declare(strict_types=1);

namespace App\Modules\Tours\Models;

use App\Modules\Orders\Models\OrderService;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Service de commande planifié sur un arrêt.
 *
 * `isActiveAssignment` distingue l'affectation courante de l'historique. Une
 * affectation remplacée est désactivée, jamais supprimée : le §13 l'exige, et
 * c'est ce qui permet de savoir a posteriori quelle tournée devait rendre un
 * service.
 *
 * `status` reste une chaîne libre — le diagramme ne l'énumère pas, et ce n'est
 * donc pas un enum.
 */
#[Fillable([
    'tour_stop_id',
    'order_service_id',
    'sequence_within_stop',
    'is_active_assignment',
    'status',
])]
class TourStopService extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'tour_stop_services';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence_within_stop' => 'integer',
            'is_active_assignment' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<TourStop, $this>
     */
    public function tourStop(): BelongsTo
    {
        return $this->belongsTo(TourStop::class, 'tour_stop_id');
    }

    /**
     * @return BelongsTo<OrderService, $this>
     */
    public function orderService(): BelongsTo
    {
        return $this->belongsTo(OrderService::class, 'order_service_id');
    }

    /**
     * @return HasMany<TourPeriodAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TourPeriodAssignment::class, 'tour_stop_service_id');
    }
}
