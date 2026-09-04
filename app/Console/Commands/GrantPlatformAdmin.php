<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Enums\RoleScope;
use Illuminate\Console\Command;

/**
 * Confère ou retire l'autorité d'administration de la plateforme.
 *
 * Désigner un administrateur de plateforme est une décision d'exploitation :
 * le rôle `superadmin` est semé mais n'est attribué à personne, et il faut une
 * action délibérée pour le rattacher. L'automatiser recréerait le défaut
 * corrigé, où chaque propriétaire d'organisme obtenait ce pouvoir sans l'avoir
 * demandé.
 *
 * Le rôle plateforme n'a pas d'organisation, mais `user_roles` pointe vers
 * `organization_users` : le rattachement s'accroche donc à une appartenance
 * existante du compte, qui n'est qu'un support technique. Il ne confère aucun
 * droit sur cette organisation en particulier — l'autorité plateforme les
 * traverse toutes.
 */
class GrantPlatformAdmin extends Command
{
    protected $signature = 'tricolis:platform-admin
                            {email : Adresse e-mail du compte}
                            {--revoke : Retirer l\'autorité au lieu de l\'accorder}';

    protected $description = 'Accorde ou retire l’administration de la plateforme à un compte existant';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $user = User::where('email', mb_strtolower($email))->first();

        if ($user === null) {
            $this->error("Aucun compte ne porte l'adresse {$email}.");

            return self::FAILURE;
        }

        $role = Role::where('scope', RoleScope::PLATFORM->value)->whereNull('organization_id')->first();

        if ($role === null) {
            $this->error('Le rôle plateforme est absent. Exécutez d’abord : php artisan db:seed --class=RoleSeeder');

            return self::FAILURE;
        }

        $membership = OrganizationUser::where('user_id', $user->id)->first();

        if ($membership === null) {
            $this->error('Ce compte n’appartient à aucune organisation : le rôle n’a rien à quoi se rattacher.');

            return self::FAILURE;
        }

        return $this->option('revoke')
            ? $this->revoke($user, $role)
            : $this->grant($user, $role, $membership);
    }

    private function grant(User $user, Role $role, OrganizationUser $membership): int
    {
        $link = UserRole::firstOrCreate([
            'organization_user_id' => $membership->id,
            'role_id' => $role->id,
        ]);

        $this->info($link->wasRecentlyCreated
            ? "{$user->email} administre désormais la plateforme."
            : "{$user->email} administrait déjà la plateforme.");

        return self::SUCCESS;
    }

    private function revoke(User $user, Role $role): int
    {
        $removed = UserRole::where('role_id', $role->id)
            ->whereIn('organization_user_id', OrganizationUser::where('user_id', $user->id)->pluck('id'))
            ->delete();

        $this->info($removed > 0
            ? "L'autorité de plateforme a été retirée à {$user->email}."
            : "{$user->email} n'administrait pas la plateforme.");

        return self::SUCCESS;
    }
}
