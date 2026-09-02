<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\SetMemberPasswordRequest;
use App\Modules\Identity\Services\SendPasswordResetLink;
use App\Modules\Integrations\Services\OrganizationMailer;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Rendre l'accès à un membre qui l'a perdu.
 *
 * **Deux chemins, parce que deux situations.** Le lien par courriel est le bon
 * par défaut : l'administrateur ne connaît jamais le mot de passe, et le membre
 * le choisit lui-même. Mais tous les comptes ne relèvent pas leur boîte — un
 * chauffeur inscrit sous une adresse de service, un compte créé pour un poste
 * partagé — et l'attente d'un courriel qui n'arrivera pas bloque la journée.
 * L'administrateur peut alors poser un mot de passe et le transmettre de vive
 * voix.
 *
 * **Les jetons du membre tombent dans les deux cas.** Un mot de passe change
 * parce qu'on soupçonne qu'il a fuité, ou parce que quelqu'un a perdu l'accès :
 * dans les deux cas, laisser vivre les sessions ouvertes viderait le geste de
 * son sens.
 *
 * Le mot de passe n'est jamais rendu ni journalisé — l'audit retient le geste,
 * jamais la valeur.
 */
class MemberPasswordController extends Controller
{
    /**
     * Envoyer un lien de réinitialisation au membre.
     *
     * Permission requise : `users.reset_password`.
     *
     * Le courriel part de la messagerie de l'organisation quand elle en a réglé
     * une, et reprend son modèle `password_reset` quand elle en a écrit un : un
     * lien signé d'un autre transporteur ne serait pas cru, et un texte signé
     * « Laravel » pas davantage.
     */
    public function sendLink(
        Request $request,
        OrganizationUser $organizationUser,
        OrganizationMailer $mailer,
        SendPasswordResetLink $sender,
    ): JsonResponse {
        $this->authorize('resetPassword', $organizationUser);

        $email = $organizationUser->user?->email;

        if (! is_string($email) || $email === '') {
            return ApiResponse::error(
                'Ce membre n’a pas d’adresse e-mail : posez-lui un mot de passe.',
                422,
            );
        }

        // La notification de repli choisit son transport par `mail.default` :
        // sans cette bascule, le lien partirait signe du serveur plutot que du
        // transporteur, et le membre ne le croirait pas.
        $mailer->useFor($organizationUser->organization_id);

        $sender->execute($organizationUser->user, $organizationUser->organization_id);

        $this->audit(
            $request,
            $organizationUser->organization_id,
            'password_reset_link_sent',
            $organizationUser,
            null,
            ['email' => $email],
        );

        return ApiResponse::ok(['email' => $email]);
    }

    /**
     * Poser un mot de passe pour le membre.
     *
     * Permission requise : `users.reset_password`. À réserver aux comptes qui
     * ne relèvent pas de courriel : l'administrateur connaît alors le mot de
     * passe, ce que le lien évite.
     */
    public function set(
        SetMemberPasswordRequest $request,
        OrganizationUser $organizationUser,
    ): JsonResponse {
        $this->authorize('resetPassword', $organizationUser);

        $user = $organizationUser->user;

        if ($user === null) {
            return ApiResponse::error('Ce rattachement n’a pas de compte.', 422);
        }

        $user->forceFill(['password' => Hash::make($request->validated('password'))])
            ->setRememberToken(Str::random(60));
        $user->save();

        // Les sessions ouvertes tombent : un mot de passe change justement pour
        // que l'acces precedent cesse.
        $user->tokens()->delete();

        // Ni le mot de passe ni son empreinte : un journal se relit longtemps
        // apres, par des gens qui n'ont pas a connaitre le secret.
        $this->audit(
            $request,
            $organizationUser->organization_id,
            'password_set_by_admin',
            $organizationUser,
            null,
            ['userId' => $user->id],
        );

        return ApiResponse::noContent();
    }
}
