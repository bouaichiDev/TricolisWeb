<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Database\Concerns\HasUlid;
use App\Shared\Enums\UserStatus;
use Database\Factories\Modules\Identity\Models\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'first_name',
    'last_name',
    'email',
    'phone',
    'password',
    'preferred_language',
    'status',
    'email_verified_at',
    'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;

    use HasFactory;
    use HasUlid;
    use Notifiable;

    public $timestamps = true;

    protected $table = 'users';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    /**
     * @return HasMany<OrganizationUser, $this>
     */
    public function organizationUsers(): HasMany
    {
        return $this->hasMany(OrganizationUser::class, 'user_id');
    }

    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_users', 'user_id', 'organization_id');
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
