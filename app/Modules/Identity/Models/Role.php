<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Organizations\Models\Organization;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'organization_id',
    'code',
    'name',
    'scope',
    'is_system',
    'status',
])]
class Role extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'roles';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
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
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id');
    }

    /**
     * @return BelongsToMany<OrganizationUser, $this>
     */
    public function organizationUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Modules\Organizations\Models\OrganizationUser::class,
            'user_roles',
            'role_id',
            'organization_user_id'
        );
    }
}
