<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Tours\Models\Tour;
use App\Modules\Tours\Models\TourStop;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Événement de suivi — donnée historique.
 *
 * Un événement ne se modifie pas : une nouvelle occurrence produit une nouvelle
 * ligne. Aucune route `PATCH` ni `DELETE` n'existe, et le modèle ne porte
 * volontairement pas d'`updated_at`.
 *
 * Seule la commande est obligatoire. Service, tournée et arrêt sont facultatifs :
 * un événement peut précéder toute planification.
 */
#[Fillable([
    'organization_id',
    'order_id',
    'order_service_id',
    'tour_id',
    'tour_stop_id',
    'event_type',
    'status',
    'description',
    'latitude',
    'longitude',
    'occurred_at',
    'created_by',
])]
class TrackingEvent extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'tracking_events';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * @return BelongsTo<OrderService, $this>
     */
    public function orderService(): BelongsTo
    {
        return $this->belongsTo(OrderService::class, 'order_service_id');
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }
}
