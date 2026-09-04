<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Models;

use App\Modules\Drivers\Models\Driver;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Shared\Database\Concerns\HasUlid;
use App\Shared\Enums\UserStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'organization_id',
    'user_id',
    'is_owner',
    'is_primary',
    'status',
    'joined_at',
])]
class OrganizationUser extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'organization_users';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_owner' => 'boolean',
            'is_primary' => 'boolean',
            'status' => UserStatus::class,
            'joined_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<UserRole, $this>
     */
    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'organization_user_id');
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'organization_user_id', 'role_id');
    }

    /**
     * Le chauffeur que ce membre est, s'il en est un.
     *
     * Le lien se fait par l'utilisateur, pas par l'appartenance : `drivers`
     * porte `user_id`. L'organisation doit donc etre precisee au chargement —
     * un meme compte peut conduire dans deux organisations.
     *
     * @return HasOne<Driver, $this>
     */
    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class, 'user_id', 'user_id');
    }
}
