<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\CustomerPortal\Models\CustomerUserRole;
use App\Modules\CustomerPortal\Services\CustomerPermissions;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\CustomerPortal\Services\CustomerRoleGuard;
use App\Modules\CustomerPortal\Services\ManagedMemberGuard;
use Illuminate\Support\Facades\DB;

/**
 * Remplace les rôles d'un membre du portail, d'un bloc.
 *
 * Le jeu envoyé est le jeu final : ce qui n'y figure pas est retiré. Le
 * plafond de délégation porte sur les rôles **ajoutés** comme sur ceux
 * conservés — on ne laisse pas en place un rôle qu'on n'aurait pas le droit
 * d'accorder, sans quoi la requête servirait à le confirmer.
 */
final readonly class AssignCustomerUserRoles
{
    public function __construct(private CustomerPortalAudit $audit) {}

    /**
     * @param  array<int, string>  $roleIds
     */
    public function execute(CustomerUser $actor, CustomerUser $target, array $roleIds, ?string $ipAddress = null): CustomerUser
    {
        ManagedMemberGuard::ensureManageable($actor, $target);

        $roles = CustomerRoleGuard::resolve($target->customer, $roleIds, CustomerPermissions::delegableFor($actor));
        $before = $target->roles()->pluck('code')->sort()->values()->all();

        DB::transaction(static function () use ($target, $roles): void {
            CustomerUserRole::where('customer_user_id', $target->id)->delete();

            foreach ($roles as $role) {
                CustomerUserRole::create(['customer_user_id' => $target->id, 'role_id' => $role->id]);
            }
        });

        $after = $roles->pluck('code')->sort()->values()->all();

        if ($before !== $after) {
            $this->audit->record($target->customer, $actor->user, 'roles_changed', $target, ['roles' => $before], ['roles' => $after], $ipAddress);
        }

        return $target->load('roles');
    }
}
