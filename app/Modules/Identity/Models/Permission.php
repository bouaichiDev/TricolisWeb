<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\CustomerPortal\Support\CustomerPortalPermissions;
use App\Modules\ProviderPortal\Support\ProviderPortalPermissions;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
     * Permissions de l'administration interne : tout sauf `customer.*` et
     * `provider.*`.
     *
     * Celles des portails partagent la table mais jamais un rôle interne ;
     * les écarter ici évite qu'un rôle `admin` ou le plafond de délégation ne
     * les ramasse avec un simple `Permission::pluck()`.
     *
     * @param  Builder<Permission>  $query
     */
    public function scopeInternal(Builder $query): void
    {
        $query->where('code', 'not like', CustomerPortalPermissions::PREFIX.'%')
            ->where('code', 'not like', ProviderPortalPermissions::PREFIX.'%');
    }

    /**
     * @param  Builder<Permission>  $query
     */
    public function scopeProviderPortal(Builder $query): void
    {
        $query->where('code', 'like', ProviderPortalPermissions::PREFIX.'%');
    }

    /**
     * @param  Builder<Permission>  $query
     */
    public function scopeCustomerPortal(Builder $query): void
    {
        $query->where('code', 'like', CustomerPortalPermissions::PREFIX.'%');
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions', 'permission_id', 'role_id');
    }
}
