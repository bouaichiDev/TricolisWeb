<?php

declare(strict_types=1);

namespace App\Modules\Drivers\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Providers\Models\Provider;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chauffeur d'un fournisseur (`Provider 1 — 0..* Driver`).
 *
 * Le modèle ne porte pas d'`organization_id` : son périmètre est celui de son
 * fournisseur. Le scope `inOrganization` est le seul point qui applique cette
 * règle, pour qu'aucune lecture ne puisse l'oublier.
 */
#[Fillable([
    'legacy_id',
    'provider_id',
    'user_id',
    'code',
    'first_name',
    'last_name',
    'phone',
    'email',
    'status',
])]
#[Hidden(['legacy_id'])]
class Driver extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'drivers';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Restreint aux chauffeurs dont le fournisseur appartient à l'organisation.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeInOrganization(Builder $query, string $organizationId): void
    {
        $query->whereHas('provider', fn (Builder $provider) => $provider->where('organization_id', $organizationId));
    }
}
