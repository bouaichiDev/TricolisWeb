<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Modules\Identity\Actions\ProvisionOrganization;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\SendPasswordResetLink;
use App\Modules\Platform\Models\AccessRequest;
use App\Shared\Enums\AccessRequestStatus;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Accepte une demande : l'organisation naît, son administrateur aussi.
 *
 * **Aucun mot de passe n'est transmis.** Le compte est créé avec un secret
 * aléatoire que personne ne lit, et le demandeur reçoit le lien qui lui fera
 * choisir le sien — celui-là même qu'envoie « Renvoyer l'accès » depuis la
 * fiche d'un membre. Expédier un mot de passe en clair par courriel le
 * laisserait dans deux boîtes et dans tous les relais du chemin, et il y
 * resterait longtemps après avoir été changé.
 *
 * **Le nom du contact est coupé en deux**, faute de mieux : le formulaire
 * public demande un nom, pas un prénom et un nom. Découper sur le premier
 * espace se trompe parfois ; imposer deux champs à quelqu'un qui n'est pas
 * encore client se trompe plus souvent. L'administrateur corrige en une
 * minute, sur une fiche qui existe enfin.
 */
final readonly class ApproveAccessRequest
{
    public function __construct(
        private ProvisionOrganization $provision,
        private SendPasswordResetLink $sendLink,
    ) {}

    public function execute(AccessRequest $request, User $decidedBy): AccessRequest
    {
        if ($request->status->isDecided()) {
            throw ValidationException::withMessages([
                'status' => ['Cette demande a déjà été tranchée.'],
            ]);
        }

        // L'adresse est la clé d'un compte : deux comptes ne peuvent pas la
        // partager, et la creation echouerait plus loin, une organisation a
        // moitie creee derriere elle.
        if (User::where('email', $request->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Un compte existe déjà avec cette adresse : rattachez-le plutôt à une organisation.'],
            ]);
        }

        [$firstName, $lastName] = $this->splitName($request->contact_name);

        $created = $this->provision->execute([
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Str::password(32),
            'organization' => [
                'name' => $request->company_name,
                'email' => $request->email,
                'phone' => $request->phone,
            ],
        ]);

        $request->update([
            'status' => AccessRequestStatus::APPROVED,
            'organization_id' => $created['organization']->id,
            'user_id' => $created['user']->id,
            'decided_by' => $decidedBy->id,
            'decided_at' => now(),
        ]);

        $this->sendLink->execute($created['user'], $created['organization']->id);

        return $request->refresh();
    }

    /**
     * @return array{string, string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        if (count($parts) < 2) {
            return ['', $parts[0] ?? $name];
        }

        return [$parts[0], $parts[1]];
    }
}
