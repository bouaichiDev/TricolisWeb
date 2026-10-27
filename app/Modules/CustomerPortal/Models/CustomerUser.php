<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Models;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Shared\Database\Concerns\HasUlid;
use App\Shared\Enums\UserStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Appartenance d'un compte au portail d'un client.
 *
 * Le statut réutilise `UserStatus`, comme `OrganizationUser` : les quatre
 * états (invité, actif, suspendu, désactivé) sont ceux de la spécification.
 */
#[Fillable([
    'customer_id',
    'user_id',
    'status',
    'is_owner',
    'invited_by',
    'invited_at',
    'activated_at',
    'disabled_at',
])]
class CustomerUser extends Model
{
    use HasUlid;

    protected $table = 'customer_users';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'is_owner' => 'boolean',
            'invited_at' => 'datetime',
            'activated_at' => 'datetime',
            'disabled_at' => 'datetime',
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
        return $this->belongsToMany(Role::class, 'customer_user_roles', 'customer_user_id', 'role_id');
    }

    /**
     * @return HasMany<CustomerUserInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(CustomerUserInvitation::class, 'customer_user_id');
    }

    /**
     * @param  Builder<CustomerUser>  $query
     */
    public function scopeForCustomer(Builder $query, string $customerId): void
    {
        $query->where('customer_id', $customerId);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }
}
