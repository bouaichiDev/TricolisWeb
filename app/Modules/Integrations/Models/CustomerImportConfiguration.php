<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Modules\Customers\Models\Customer;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuration d'import d'un client.
 *
 * Le diagramme définit une **configuration**, pas un historique d'import : ni
 * fichier, ni ligne, ni erreur.
 *
 * `mapping` et `validationRules` sont des structures librement configurables —
 * le diagramme n'en définit pas le schéma, et le §9 interdit de l'inventer. Ce
 * sont des données lues par un futur moteur d'import, jamais évaluées.
 */
#[Fillable([
    'customer_id',
    'name',
    'source_type',
    'file_format',
    'mapping',
    'validation_rules',
    'is_active',
])]
class CustomerImportConfiguration extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'customer_import_configurations';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'validation_rules' => 'array',
            'is_active' => 'boolean',
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
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->whereHas('customer', fn (Builder $c) => $c->where('organization_id', $organizationId));
    }
}
