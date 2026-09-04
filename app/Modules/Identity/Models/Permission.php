<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'code',
    'name',
    'module',
    'menu_section',
    'action',
])]
class Permission extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'permissions';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions', 'permission_id', 'role_id');
    }
}
