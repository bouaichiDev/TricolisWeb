<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Modules\Customers\Models\Customer;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuration d'accès API d'un client.
 *
 * `api_key_hash` est une empreinte SHA-256 : déterministe, donc consultable par
 * index unique à chaque requête. Elle est marquée `#[Hidden]` — aucune Resource
 * ne la restitue, et une sérialisation accidentelle ne la révélerait pas.
 *
 * La clé en clair n'est jamais stockée : elle est retournée une seule fois, à la
 * création ou à la rotation.
 */
#[Fillable([
    'customer_id',
    'name',
    'api_key_hash',
    'allowed_ips',
    'permissions',
    'is_active',
    'last_used_at',
])]
#[Hidden(['api_key_hash'])]
class CustomerApiConfiguration extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'customer_api_configurations';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_ips' => 'array',
            'permissions' => 'array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
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
