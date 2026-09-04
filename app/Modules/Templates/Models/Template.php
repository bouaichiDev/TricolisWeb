<?php

declare(strict_types=1);

namespace App\Modules\Templates\Models;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Communications\Models\CommunicationRule;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Service;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Templates\Enums\TemplateType;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle réutilisable — message ou document.
 *
 * `availableVariables` déclare les seules variables que le rendu acceptera de
 * remplacer : une variable écrite dans le corps mais absente de cette liste
 * fait échouer le rendu, jamais un remplacement silencieux.
 *
 * `customer_id` porte la personnalisation : nul, le modèle vaut pour toute
 * l'organisation ; renseigné, il ne vaut que pour ce client. `ResolveTemplateAction`
 * choisit entre les deux, et ne sert jamais celui d'un tiers.
 */
#[Fillable([
    'organization_id',
    'customer_id',
    'service_id',
    'code',
    'name',
    'channel',
    'template_type',
    'subject_template',
    'body_template',
    'body_format',
    'language',
    'available_variables',
    'is_default',
    'is_active',
])]
class Template extends Model
{
    use HasFactory;
    use HasUlid;

    protected $table = 'templates';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => CommunicationChannel::class,
            'template_type' => TemplateType::class,
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
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
     * Factures ayant été rendues avec ce modèle.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'template_id');
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

    /** Portée du modèle, telle que l'interface l'affiche. */
    public function scope(): string
    {
        return $this->customer_id === null ? 'global' : 'customer';
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }
}
