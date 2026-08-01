<?php

declare(strict_types=1);

namespace App\Modules\Tours\Models;

use App\Modules\Packages\Models\Package;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Affectation d'un service — et éventuellement d'un colis — à une période.
 *
 * Le diagramme ne pose que trois clés étrangères sur cette classe : ni
 * séquence, ni statut, ni quantité, ni durée. Le §17 l'interdit explicitement.
 */
#[Fillable([
    'tour_period_id',
    'tour_stop_service_id',
    'package_id',
])]
class TourPeriodAssignment extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'tour_period_assignments';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return BelongsTo<TourPeriod, $this>
     */
    public function tourPeriod(): BelongsTo
    {
        return $this->belongsTo(TourPeriod::class, 'tour_period_id');
    }

    /**
     * @return BelongsTo<TourStopService, $this>
     */
    public function tourStopService(): BelongsTo
    {
        return $this->belongsTo(TourStopService::class, 'tour_stop_service_id');
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }
}
