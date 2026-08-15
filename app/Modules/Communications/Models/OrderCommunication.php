<?php

declare(strict_types=1);

namespace App\Modules\Communications\Models;

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Communications\Enums\CommunicationTemplateType;
use App\Modules\Communications\Enums\RecipientRole;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Organizations\Models\Organization;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Message rattaché à une commande — donnée historique.
 *
 * `subject`, `body`, `template_variables` et les trois champs `recipient_*`
 * sont des snapshots : ils décrivent ce qui a été envoyé, pas ce que le template
 * dit aujourd'hui. Modifier un template ou une règle ne les touche jamais.
 *
 * Les champs d'exécution — statut, horodatages, réponse fournisseur — ne sont
 * jamais renseignés depuis une requête HTTP : seules les Actions et le Job les
 * écrivent.
 */
#[Fillable([
    'organization_id',
    'order_id',
    'template_id',
    'communication_rule_id',
    'channel',
    'communication_type',
    'recipient_role',
    'recipient_name',
    'recipient_email',
    'recipient_phone',
    'subject',
    'body',
    'template_variables',
    'status',
    'scheduled_at',
    'created_by',
])]
class OrderCommunication extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'order_communications';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => CommunicationChannel::class,
            'communication_type' => CommunicationTemplateType::class,
            'recipient_role' => RecipientRole::class,
            'status' => CommunicationStatus::class,
            'template_variables' => 'array',
            'provider_response' => 'array',
            'scheduled_at' => 'datetime',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
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
     * @return BelongsTo<CommunicationTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'template_id');
    }

    /**
     * @return BelongsTo<CommunicationRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommunicationRule::class, 'communication_rule_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<CommunicationAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(CommunicationAttachment::class, 'communication_id');
    }

    public function allowsContentChanges(): bool
    {
        return $this->status->allowsContentChanges();
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }
}
