<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Customers\Models\Customer;
use App\Modules\Organizations\Models\Organization;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Facture client (`Customer "1" -- "0..*" Invoice`).
 *
 * Racine d'agrégat : `Invoice "1" *-- "1..*" InvoiceLine`. Une facture sans
 * ligne n'existe pas au modèle, d'où la création atomique et le refus de
 * supprimer la dernière ligne.
 *
 * Les trois totaux sont **dérivés des lignes**, jamais fournis par l'appelant.
 *
 * `created_at` existe parce que le diagramme déclare `createdAt` ; `updated_at`
 * non, pour la même raison — d'où `$timestamps = false`.
 */
#[Fillable([
    'organization_id',
    'customer_id',
    'invoice_number',
    'invoice_date',
    'period_from',
    'period_to',
    'currency_code',
    'subtotal',
    'tax_total',
    'total',
    'external_reference',
    'remark',
    'status',
    'created_at',
])]
class Invoice extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'invoices';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'period_from' => 'date',
            'period_to' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'created_at' => 'datetime',
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
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'invoice_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }
}
