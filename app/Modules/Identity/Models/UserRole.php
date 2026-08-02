<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_user_id',
    'role_id',
])]
class UserRole extends Model
{
    use HasUlid;

    public $timestamps = false;

    protected $table = 'user_roles';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return BelongsTo<OrganizationUser, $this>
     */
    public function organizationUser(): BelongsTo
    {
        return $this->belongsTo(OrganizationUser::class, 'organization_user_id');
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
