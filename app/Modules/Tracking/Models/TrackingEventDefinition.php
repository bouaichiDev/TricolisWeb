<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Une étape du parcours client, déclenchée par un statut.
 *
 * `sourceType` désigne la table qui porte le statut — `order_service`, `tour`,
 * `package` — et `statusCode` le statut qui fait apparaître l'étape.
 */
#[Fillable([
    'organization_id',
    'source_type',
    'status_code',
    'code',
    'title',
    'description',
    'icon',
    'position',
    'is_live',
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
            'is_live' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }
}
