<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Actions;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Models\ProviderUserRole;
use App\Modules\ProviderPortal\Services\ProviderPortalAudit;
use Illuminate\Support\Facades\DB;

/**
 * Remplace le rôle d'un membre du portail fournisseur.
 *
 * Un membre porte un seul rôle : le changer retire les autres. Le rôle est
 * vérifié par l'appelant (portée `provider`, même organisation).
 */
final readonly class AssignProviderUserRole
{
    public function __construct(private ProviderPortalAudit $audit) {}

    public function execute(ProviderUser $target, Role $role, ?User $actor, ?string $ipAddress = null): ProviderUser
    {
        $before = $target->roles()->pluck('roles.code')->all();

        if ($before === [$role->code]) {
            return $target->load(['user', 'roles']);
        }

        DB::transaction(function () use ($target, $role): void {
            ProviderUserRole::where('provider_user_id', $target->id)->delete();
            ProviderUserRole::create(['provider_user_id' => $target->id, 'role_id' => $role->id]);
        });

        $this->audit->record($target->provider, $actor, 'role_changed', $target, ['roles' => $before], ['roles' => [$role->code]], $ipAddress);

        return $target->load(['user', 'roles']);
    }
}
