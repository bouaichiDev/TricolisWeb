<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Sources;

use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Exceptions\PermanentIntegrationFailure;
use App\Shared\Enums\UserStatus;

/**
 * L'utilisateur au nom duquel un import automatique crée les commandes.
 *
 * `orders.created_by` est `NOT NULL` et référence `users` : une commande a
 * toujours un auteur, et l'audit le nomme. Un import planifié n'a pas de
 * session — l'auteur est donc **désigné par la configuration de la
 * connexion** (`actorUserId` pour un pull REST, `actorEmail` pour une relève
 * FTP/SFTP), jamais deviné.
 *
 * Il doit être un utilisateur **actif**, **membre de l'organisation** de la
 * configuration, et **interne** : un compte du portail client ne crée pas de
 * commandes au nom du transporteur.
 */
final readonly class IntegrationActor
{
    /**
     * @throws PermanentIntegrationFailure aucun auteur valide
     */
    public function resolve(string $organizationId, ?string $userId, ?string $email): User
    {
        $user = match (true) {
            $userId !== null && $userId !== '' => User::find($userId),
            $email !== null && $email !== '' => User::where('email', $email)->first(),
            default => null,
        };

        if ($user === null) {
            throw new PermanentIntegrationFailure('Aucun utilisateur d’exécution n’est désigné pour cet import automatique.');
        }

        $member = $user->organizations()->whereKey($organizationId)->exists();
        $portal = CustomerUser::where('user_id', $user->id)->exists();

        if ($user->status !== UserStatus::ACTIVE || ! $member || $portal) {
            throw new PermanentIntegrationFailure('L’utilisateur d’exécution désigné n’est pas un utilisateur interne actif de l’organisation.');
        }

        return $user;
    }
}
