<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use App\Modules\Orders\Models\Service;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une étape du parcours client, déclenchée par un statut.
 *
 * `sourceType` désigne la table qui porte le statut — `order_service`, `tour`,
 * `package` — et `statusCode` le statut qui fait apparaître l'étape.
 */
#[Fillable([
    'organization_id',
    'source_type',
    'service_id',
    'status_code',
    'code',
    'title',
    'description',
    'icon',
    'position',
    'api_configuration_id',
    'visible_to_customer',
    'shows_proof_of_delivery',
    'active',
])]
class TrackingEventDefinition extends Model
{
    use HasUlid;

    protected $table = 'tracking_event_definitions';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'active' => 'boolean',
            'visible_to_customer' => 'boolean',
            'shows_proof_of_delivery' => 'boolean',
        ];
    }

    /**
     * La prestation dont cette etape parle, quand elle en vise une.
     *
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /** @param  Builder<self>  $query */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }
}
