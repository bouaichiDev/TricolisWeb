<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Addresses\Models\Address;
use App\Modules\Billing\Models\InvoiceLine;
use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Packages\Models\Package;
use App\Modules\ProviderSettlements\Models\ProviderSettlementLine;
use App\Modules\Tours\Models\TourStopService;
use App\Modules\Tracking\Models\Concerns\TracksStatusChanges;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Service d'une commande — unité principale de planification.
 *
 * Il porte sa propre adresse, ses contacts, ses colis, son créneau demandé,
 * son prix client et son coût fournisseur. Les champs financiers sont
 * enregistrés tels que fournis : aucun prix n'est calculé à ce stade.
 */
#[Fillable([
    'order_id',
    'service_id',
    'address_id',
    'service_number',
    'sequence',
    'requested_date',
    'requested_from',
    'requested_to',
    'quantity',
    'unit',
    'required_time_minutes',
    'remaining_time_minutes',
    'weight',
    'volume',
    'package_count',
    'customer_unit_price',
    'customer_total_price',
    'provider_unit_cost',
    'provider_total_cost',
    'instructions',
    'status',
])]
class OrderService extends Model
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
            'requested_date' => 'date',
            'requested_from' => 'datetime',
            'requested_to' => 'datetime',
            'status' => OrderServiceStatus::class,
            'quantity' => 'decimal:3',
            'weight' => 'decimal:3',
            'volume' => 'decimal:4',
            'package_count' => 'integer',
            'sequence' => 'integer',
            'required_time_minutes' => 'integer',
            'remaining_time_minutes' => 'integer',
            'customer_unit_price' => 'decimal:2',
            'customer_total_price' => 'decimal:2',
            'provider_unit_cost' => 'decimal:2',
            'provider_total_cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<Address, $this>
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * @return HasMany<OrderServiceContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(OrderServiceContact::class, 'order_service_id');
    }

    /**
     * @return HasMany<OrderServicePackage, $this>
     */
    public function servicePackages(): HasMany
    {
        return $this->hasMany(OrderServicePackage::class, 'order_service_id');
    }

    /**
     * @return BelongsToMany<Package, $this>
     */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'order_service_packages', 'order_service_id', 'package_id')
            ->withPivot(['id', 'quantity', 'handling_instructions', 'status']);
    }

    /**
     * Affectations de ce service, actives comme historiques.
     *
     * Un service peut avoir ete planifie plusieurs fois : c'est la
     * replanification. Une seule de ces affectations est active a la fois.
     *
     * @return HasMany<TourStopService, $this>
     */
    /**
     * La ligne de facture qui porte ce service, s'il est facturé.
     *
     * `hasOne` et non `hasMany` : le §10 impose qu'un service ne soit facturé
     * qu'une fois, et la base le tient par une contrainte d'unicité.
     *
     * @return HasOne<InvoiceLine, $this>
     */
    public function invoiceLine(): HasOne
    {
        return $this->hasOne(InvoiceLine::class, 'order_service_id');
    }

    /**
     * La ligne de décompte qui règle ce service à un fournisseur.
     *
     * Même raison, §16 : un service ne se règle pas deux fois.
     *
     * @return HasOne<ProviderSettlementLine, $this>
     */
    public function settlementLine(): HasOne
    {
        return $this->hasOne(ProviderSettlementLine::class, 'order_service_id');
    }

    public function tourStopServices(): HasMany
    {
        return $this->hasMany(TourStopService::class, 'order_service_id');
    }
}
