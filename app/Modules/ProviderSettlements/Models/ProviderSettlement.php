<?php

declare(strict_types=1);

namespace App\Modules\ProviderSettlements\Models;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Providers\Models\Provider;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Décompte fournisseur (`Provider "1" -- "0..*" ProviderSettlement`).
 *
 * Racine d'agrégat : `ProviderSettlement "1" *-- "1..*" ProviderSettlementLine`.
 *
 * `subtotal` et `total` sont dérivés des lignes ; `tax_total` est **saisi** —
 * le §21 interdit d'inventer une TVA fournisseur, et aucune règle fiscale n'est
 * définie au modèle.
 *
 * Aucun timestamp : la classe n'en déclare aucun, à la différence d'`Invoice`.
 */
#[Fillable([
    'organization_id',
    'provider_id',
    'settlement_number',
    'period_from',
    'period_to',
    'subtotal',
    'tax_total',
    'total',
    'status',
])]
class ProviderSettlement extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'provider_settlements';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
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
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    /**
     * @return HasMany<ProviderSettlementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ProviderSettlementLine::class, 'settlement_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }
}
