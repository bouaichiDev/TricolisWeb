<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\Depot;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Packages\Models\Package;
use App\Modules\Statuses\Services\StatusMachine;
use App\Modules\Tracking\Models\Concerns\TracksStatusChanges;
use App\Shared\Database\Concerns\HasUlid;
use App\Shared\Database\MorphMap;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Commande de transport.
 *
 * L'adresse n'est pas portée par la commande : chaque `OrderService` a la
 * sienne, conformément au diagramme. Les arrêts physiques (`TourStop`) sont
 * produits par la planification, dans une phase ultérieure.
 */
#[Fillable([
    'organization_id',
    'customer_id',
    'agency_id',
    'depot_id',
    'parent_order_id',
    'order_number',
    'external_reference',
    'customer_reference',
    'order_type',
    'group_code',
    'order_date',
    'source',
    'internal_remark',
    'worker_remark',
    'weight',
    'volume',
    'package_count',
    'currency_code',
    'status',
    'created_by',
    'updated_by',
])]
class Order extends Model
{
    use HasFactory;
    use HasUlid;
    use TracksStatusChanges;

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_date' => 'datetime',
            'source' => OrderSource::class,
            'status' => OrderStatus::class,
            'weight' => 'decimal:3',
            'volume' => 'decimal:4',
            'package_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return BelongsTo<Depot, $this>
     */
    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
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
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /**
     * @return HasMany<OrderService, $this>
     */
    public function orderServices(): HasMany
    {
        return $this->hasMany(OrderService::class);
    }

    /**
     * @return HasMany<Package, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_order_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_order_id');
    }

    /**
     * Le contenu de la commande est-il encore modifiable ?
     *
     * La reponse vient du referentiel des statuts, pas de l'enumeration : c'est
     * l'administrateur plateforme qui decide jusqu'ou lignes, colis et services
     * restent ouverts, et une regle figee dans le code contredirait son ecran.
     */
    public function allowsContentChanges(): bool
    {
        /** @var StatusMachine $machine */
        $machine = Container::getInstance()->make(StatusMachine::class);

        return $machine->allowsContentChanges(MorphMap::ORDER, $this->status?->value);
    }
}
