<?php

declare(strict_types=1);

namespace App\Modules\Exports\Models;

use App\Modules\Customers\Models\Customer;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Exécution d'un export — donnée historique.
 *
 * Aucune route `PATCH` ni `DELETE` : le §26 réserve la mise à jour au
 * traitement interne. Seul `retry` agit dessus, en incrémentant `attemptCount`.
 *
 * `customerId` est redondant avec `configuration.customer_id`. Le §24 interdit
 * de supprimer cette redondance : la valeur est forcée à celle de la
 * configuration, jamais acceptée en entrée.
 *
 * `entityType` porte un alias de la morph map. Aucune relation polymorphe
 * Eloquent n'est déclarée — la colonne n'a pas de clé étrangère et peut
 * désigner plusieurs tables.
 */
#[Fillable([
    'customer_id',
    'configuration_id',
    'entity_type',
    'entity_id',
    'file_name',
    'storage_path',
    'status',
    'attempt_count',
    'generated_at',
    'sent_at',
    'error_message',
])]
class ExportJob extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'export_jobs';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'generated_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * @return BelongsTo<CustomerExportConfiguration, $this>
     */
    public function configuration(): BelongsTo
    {
        return $this->belongsTo(CustomerExportConfiguration::class, 'configuration_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->whereHas('customer', fn (Builder $c) => $c->where('organization_id', $organizationId));
    }
}
