<?php

declare(strict_types=1);

namespace App\Modules\Communications\Models;

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Communications\Enums\CommunicationTemplateType;
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
 * Modèle de message réutilisable.
 *
 * `availableVariables` déclare les seules variables que le rendu acceptera de
 * remplacer : une variable écrite dans le corps mais absente de cette liste
 * fait échouer le rendu, jamais un remplacement silencieux.
 */
#[Fillable([
    'organization_id',
    'service_id',
    'code',
    'name',
    'channel',
    'template_type',
    'subject_template',
    'body_template',
    'language',
    'available_variables',
    'is_default',
    'is_active',
])]
class CommunicationTemplate extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'communication_templates';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => CommunicationChannel::class,
            'template_type' => CommunicationTemplateType::class,
            'available_variables' => 'array',
            'is_default' => 'boolean',
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
     * @return HasMany<CommunicationRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(CommunicationRule::class, 'template_id');
    }

    /**
     * @return HasMany<OrderCommunication, $this>
     */
    public function communications(): HasMany
    {
        return $this->hasMany(OrderCommunication::class, 'template_id');
    }

    /**
     * Variables déclarées, toujours sous forme de liste de chaînes.
     *
     * @return list<string>
     */
    public function declaredVariables(): array
    {
        $variables = $this->available_variables;

        if (! is_array($variables)) {
            return [];
        }

        return array_values(array_filter($variables, 'is_string'));
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }
}
