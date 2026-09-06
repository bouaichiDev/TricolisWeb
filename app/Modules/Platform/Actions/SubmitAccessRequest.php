<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Mail\AccessRequestSubmittedMail;
use App\Modules\Platform\Models\AccessRequest;
use App\Shared\Enums\AccessRequestStatus;
use App\Shared\Enums\RoleScope;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Enregistre une demande d'accès et prévient la plateforme.
 *
 * **La demande est enregistrée d'abord, l'avis part ensuite.** L'ordre n'est
 * pas indifférent : un serveur de courriel indisponible ne doit pas faire
 * perdre la demande de quelqu'un qui, lui, a rempli le formulaire correctement.
 * L'échec d'envoi est journalisé et la demande reste en attente — elle est
 * visible sur l'écran de la plateforme, qui ne dépend d'aucun courriel.
 *
 * **Qui est prévenu ?** Tout compte portant un rôle de portée `PLATFORM` —
 * la même définition que celle qui donne l'autorité plateforme partout
 * ailleurs. Chercher un « super admin » par son nom de rôle ferait dépendre
 * l'avis d'un libellé que n'importe qui peut renommer.
 */
final readonly class SubmitAccessRequest
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): AccessRequest
    {
        $request = AccessRequest::create([
            'company_name' => $data['companyName'],
            'contact_name' => $data['contactName'],
            'email' => Str::lower($data['email']),
            'phone' => $data['phone'],
            'message' => $data['message'] ?? null,
            'status' => AccessRequestStatus::PENDING,
        ]);

        $this->notifyPlatform($request);

        return $request;
    }

    private function notifyPlatform(AccessRequest $request): void
    {
        $recipients = User::whereHas(
            'organizationUsers.roles',
            static fn ($query) => $query->where('scope', RoleScope::PLATFORM->value),
        )->pluck('email')->filter()->unique()->all();

        if ($recipients === []) {
            return;
        }

        try {
            Mail::to($recipients)->send(new AccessRequestSubmittedMail($request));
        } catch (Throwable $exception) {
            // Sans coordonnées dans le journal : la demande est en base, et un
            // journal se relit par des gens qui n'ont pas à connaître le
            // téléphone d'un demandeur.
            Log::warning('Avis de demande d’accès non distribué', [
                'accessRequest' => $request->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
