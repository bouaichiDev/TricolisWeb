<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Models;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Providers\Models\Provider;
use App\Shared\Database\Concerns\HasUlid;
use App\Shared\Enums\UserStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Appartenance d'un compte au portail d'un fournisseur.
 *
 * Le statut réutilise `UserStatus`, comme `CustomerUser` : invité, actif,
 * suspendu, désactivé. Seul l'état actif ouvre le portail.
 */
#[Fillable([
    'provider_id',
    'user_id',
    'status',
    'invited_by',
    'invited_at',
    'activated_at',
    'disabled_at',
])]
class ProviderUser extends Model
{
    use HasUlid;

    protected $table = 'provider_users';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'invited_at' => 'datetime',
            'activated_at' => 'datetime',
            'disabled_at' => 'datetime',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'provider_user_roles', 'provider_user_id', 'role_id');
    }

    /**
     * @return HasMany<ProviderUserInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(ProviderUserInvitation::class, 'provider_user_id');
    }

    /**
     * @param  Builder<ProviderUser>  $query
     */
    public function scopeForProvider(Builder $query, string $providerId): void
    {
        $query->where('provider_id', $providerId);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }
}
