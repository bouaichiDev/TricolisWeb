<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\Organizations\Models\OrganizationUser;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Corrige l'identité d'un membre : nom, prénom, téléphone.
 *
 * **Seulement si le compte n'appartient qu'à ce client.** Un `User` partagé —
 * collaborateur d'un transporteur, membre d'un autre client — n'est pas la
 * propriété de cet administrateur : le laisser renommer un compte qui sert
 * ailleurs lui donnerait prise sur l'identité affichée chez des tiers.
 * L'adresse email, qui est l'identifiant de connexion, n'est jamais modifiable
 * par ce chemin.
 */
final readonly class UpdateCustomerUser
{
    private const array FIELDS = ['firstName' => 'first_name', 'lastName' => 'last_name', 'phone' => 'phone'];

    public function __construct(private CustomerPortalAudit $audit) {}

    /**
     * @param  array{firstName?: string, lastName?: string, phone?: string|null}  $data
     */
    public function execute(CustomerUser $actor, CustomerUser $target, array $data, ?string $ipAddress = null): CustomerUser
    {
        $user = $target->user;

        if ($this->isShared($target)) {
            throw ValidationException::withMessages(['member' => ['Ce compte est aussi utilisé en dehors de votre portail : seul son titulaire peut modifier son identité.']]);
        }

        $changes = [];

        foreach (self::FIELDS as $input => $column) {
            if (array_key_exists($input, $data)) {
                $changes[$column] = $data[$input];
            }
        }

        $old = Arr::only($user->getAttributes(), array_keys($changes));
        $user->fill($changes)->save();

        if ($user->wasChanged()) {
            $this->audit->record($target->customer, $actor->user, 'user_updated', $target, $old, $user->getChanges(), $ipAddress);
        }

        return $target->load('user');
    }

    private function isShared(CustomerUser $target): bool
    {
        return OrganizationUser::where('user_id', $target->user_id)->exists()
            || CustomerUser::where('user_id', $target->user_id)->where('id', '!=', $target->id)->exists();
    }
}
