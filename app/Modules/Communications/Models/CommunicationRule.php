<?php

declare(strict_types=1);

namespace App\Modules\Communications\Models;

use App\Modules\Communications\Enums\CommunicationEventType;
use App\Modules\Communications\Enums\RecipientRole;
use App\Modules\Orders\Models\Service;
use App\Modules\Organizations\Models\Organization;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Règle liant un événement métier à un template et à un rôle de destinataire.
 *
 * Aucun déclencheur automatique n'existe encore : les onze
 * `CommunicationEventType` ne sont émis par aucune phase antérieure. La règle
 * est enregistrée, validée et évaluable ; son branchement attend les événements.
 */
#[Fillable([
    'organization_id',
    'service_id',
    'template_id',
    'event_type',
    'recipient_role',
    'delay_value',
    'delay_unit',
    'conditions',
    'is_automatic',
    'is_active',
])]
class CommunicationRule extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'communication_rules';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => CommunicationEventType::class,
            'recipient_role' => RecipientRole::class,
            'delay_value' => 'integer',
            'conditions' => 'array',
            'is_automatic' => 'boolean',
            'is_active' => 'boolean',
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
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * @return BelongsTo<CommunicationTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'template_id');
    }

    /**
     * @return HasMany<OrderCommunication, $this>
     */
    public function communications(): HasMany
    {
        return $this->hasMany(OrderCommunication::class, 'communication_rule_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }
}
