<?php

declare(strict_types=1);

namespace App\Modules\Exports\Models;

use App\Modules\Customers\Models\Customer;
use App\Modules\Exports\Enums\ExportFormat;
use App\Modules\Exports\Enums\ExportTransport;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Configuration d'export d'un client.
 *
 * `encrypted_password` est marqué `#[Hidden]` : ni sa forme chiffrée ni sa forme
 * claire ne sortent jamais. La Resource expose un booléen `hasPassword`.
 *
 * Les champs de connexion sont tous facultatifs — leur nécessité dépend du
 * transport, et le §19 interdit d'ajouter des colonnes par transport.
 */
#[Fillable([
    'customer_id',
    'name',
    'export_type',
    'format',
    'transport',
    'host',
    'port',
    'username',
    'encrypted_password',
    'remote_directory',
    'file_name_pattern',
    'encoding',
    'frequency',
    'settings',
    'is_active',
])]
#[Hidden(['encrypted_password'])]
class CustomerExportConfiguration extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'customer_export_configurations';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => ExportFormat::class,
            'transport' => ExportTransport::class,
            'port' => 'integer',
            'settings' => 'array',
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
     * @return HasMany<ExportJob, $this>
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(ExportJob::class, 'configuration_id');
    }

    /**
     * Un mot de passe est-il enregistré ?
     *
     * Seule information exposée à son sujet : la valeur elle-même ne sort
     * jamais, chiffrée ou non.
     */
    public function hasPassword(): bool
    {
        return $this->encrypted_password !== null;
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->whereHas('customer', fn (Builder $c) => $c->where('organization_id', $organizationId));
    }
}
