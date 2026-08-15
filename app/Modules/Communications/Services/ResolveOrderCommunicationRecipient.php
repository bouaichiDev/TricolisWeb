<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services;

use App\Modules\Communications\Enums\RecipientRole;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderServiceContact;
use App\Shared\Enums\ContactRole;
use Illuminate\Validation\ValidationException;

/**
 * Détermine le destinataire d'une communication à partir du rôle demandé.
 *
 * N'utilise que des données déjà existantes : le client de la commande, les
 * contacts de ses services, ou l'utilisateur authentifié. Aucune table de
 * destinataires n'est créée (§22).
 *
 * Pour les cinq rôles non-`CUSTOM`, les coordonnées fournies dans le payload
 * sont **ignorées** : le rôle est la source de vérité, sinon `recipientRole`
 * deviendrait décoratif.
 *
 * Si aucun contact ne porte le rôle demandé, la création est **refusée**. Elle
 * n'est pas basculée silencieusement sur le client : substituer un destinataire
 * est plus grave qu'échouer.
 */
final readonly class ResolveOrderCommunicationRecipient
{
    /**
     * @param  array{name?: string|null, email?: string|null, phone?: string|null}  $explicit
     *
     * @throws ValidationException
     */
    public function resolve(RecipientRole $role, Order $order, ?User $user, array $explicit = []): ResolvedRecipient
    {
        return match ($role) {
            RecipientRole::CUSTOM => $this->fromPayload($explicit),
            RecipientRole::CUSTOMER => $this->fromCustomer($order),
            RecipientRole::INTERNAL_USER => $this->fromUser($user),
            default => $this->fromContact($order, $role),
        };
    }

    /**
     * @param  array{name?: string|null, email?: string|null, phone?: string|null}  $explicit
     */
    private function fromPayload(array $explicit): ResolvedRecipient
    {
        $name = $explicit['name'] ?? null;

        if ($name === null || trim($name) === '') {
            $this->fail('recipientName', 'Un destinataire libre exige un nom explicite.');
        }

        return new ResolvedRecipient($name, $explicit['email'] ?? null, $explicit['phone'] ?? null);
    }

    private function fromCustomer(Order $order): ResolvedRecipient
    {
        $customer = $order->customer;

        if ($customer === null) {
            $this->fail('recipientRole', 'La commande n’a pas de client rattaché.');
        }

        return new ResolvedRecipient($customer->name, $customer->email, $customer->phone);
    }

    private function fromUser(?User $user): ResolvedRecipient
    {
        if ($user === null) {
            $this->fail('recipientRole', 'Aucun utilisateur interne n’est identifié pour cette communication.');
        }

        $name = trim($user->first_name.' '.$user->last_name);

        return new ResolvedRecipient($name === '' ? $user->email : $name, $user->email, null);
    }

    /**
     * Contact de commande portant le rôle demandé.
     *
     * Les colonnes lues sont les **snapshots** de `OrderServiceContact`, pas le
     * `Contact` partagé : la commande doit rester lisible telle qu'elle a été
     * passée. Le contact principal est préféré ; à défaut, le plus ancien.
     */
    private function fromContact(Order $order, RecipientRole $role): ResolvedRecipient
    {
        $contactRole = $role->contactRole();

        if (! $contactRole instanceof ContactRole) {
            $this->fail('recipientRole', 'Ce rôle de destinataire ne correspond à aucun contact de commande.');
        }

        $contact = OrderServiceContact::query()
            ->whereHas('orderService', fn ($query) => $query->where('order_id', $order->id))
            ->where('contact_role', $contactRole->value)
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->first();

        if ($contact === null) {
            $this->fail(
                'recipientRole',
                "Aucun contact « {$role->label()} » n’est rattaché aux services de cette commande.",
            );
        }

        $name = trim($contact->first_name_snapshot.' '.$contact->last_name_snapshot);

        return new ResolvedRecipient(
            $name === '' ? $role->label() : $name,
            $contact->email_snapshot,
            $contact->phone_snapshot ?? $contact->mobile_snapshot,
        );
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
