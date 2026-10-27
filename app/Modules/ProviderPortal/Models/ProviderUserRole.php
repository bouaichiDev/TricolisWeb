<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Models;

use App\Modules\Identity\Models\Role;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot `provider_user_roles`, écrit par le modèle : il porte un ULID sans
 * valeur par défaut, que `sync()` ne saurait pas fournir.
 */
#[Fillable([
    'provider_user_id',
    'role_id',
])]
class ProviderUserRole extends Model
{
    use HasUlid;

    public $timestamps = false;

    protected $table = 'provider_user_roles';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return BelongsTo<ProviderUser, $this>
     */
    public function providerUser(): BelongsTo
    {
        return $this->belongsTo(ProviderUser::class, 'provider_user_id');
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
