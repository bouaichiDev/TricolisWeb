<?php

declare(strict_types=1);

namespace App\Modules\Claims\Models;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Tours\Models\Tour;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réclamation client.
 *
 * Seul le client est obligatoire (`Customer "1" -- "0..*" Claim`) : commande,
 * service et tournée sont facultatifs, une réclamation pouvant viser la
 * prestation globale.
 *
 * `created_at` existe parce que le diagramme le déclare ; `updated_at` non, pour
 * la même raison. La date de création est posée explicitement par l'Action, d'où
 * `$timestamps = false`.
 *
 * Ni `claimNumber`, ni `severity` : absents du diagramme.
 */
#[Fillable([
    'organization_id',
    'customer_id',
    'order_id',
    'order_service_id',
    'tour_id',
    'title',
    'description',
    'claim_type',
    'cause',
    'decision',
    'follow_up',
    'result',
    'cost',
    'status',
    'created_by',
    'responsible_user_id',
    'created_at',
    'closed_at',
])]
class Claim extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'claims';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'created_at' => 'datetime',
            'closed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * Une réclamation clôturée est un dossier tranché : elle ne se supprime pas.
     *
     * `closed_at` est le seul critère objectif porté par le modèle — aucune
     * valeur de `status` n'est interprétée, le diagramme n'en énumère aucune.
     */
    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }
}
