<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services;

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ce que le bandeau supérieur montre sous la cloche.
 *
 * **Aucune table nouvelle.** Le domaine porte déjà les deux notions que le
 * besoin nomme, et il les porte depuis la Phase 9 :
 *
 * - `CommunicationChannel::INTERNAL_NOTIFICATION` — la notification **interne**,
 *   qui n'appelle aucun tiers. `InternalCommunicationSender` le dit en toutes
 *   lettres : « une notification interne **est** la ligne
 *   `order_communications` elle-même » ;
 * - les autres canaux — courriel, SMS, WhatsApp — sont les envois **externes**.
 *
 * Créer une table `notifications` par-dessus aurait fait un second journal des
 * mêmes événements, à tenir synchronisé avec le premier.
 *
 * ---
 *
 * Les deux moitiés ne se ressemblent pas, et le panneau les sépare pour cette
 * raison :
 *
 * | | Interne | Externe |
 * | --- | --- | --- |
 * | À qui | **à moi**, par mon adresse | à un client ou un contact |
 * | Ce qu'on montre | tout | seulement ce qui **a échoué** |
 * | État de lecture | `read_at`, propre à moi | aucun — l'envoi n'est adressé à personne d'ici |
 * | Permission | aucune : elle m'est adressée | `order_communications.view` |
 *
 * Un envoi réussi n'a rien à faire dans une cloche : il n'appelle aucune
 * action, et noierait les échecs qui, eux, en appellent une.
 */
final readonly class UserNotifications
{
    /** Au-delà, la liste devient un écran ; l'historique en est un. */
    private const int LIMIT = 10;

    /**
     * Les notifications qui me sont adressées.
     *
     * Le destinataire se reconnaît à son **adresse**, parce que c'est ce que
     * `ResolveOrderCommunicationRecipient::fromUser()` écrit : la table ne porte
     * pas de `recipient_user_id`. Cela suffit ici — l'adresse d'un compte est
     * unique dans `users` — mais c'est aussi la raison pour laquelle un compte
     * sans adresse ne reçoit rien, ce que la comparaison stricte garantit
     * plutôt que de tout lui rendre.
     *
     * @return Builder<OrderCommunication>
     */
    public function internalFor(User $user, ?string $organizationId): Builder
    {
        if ($organizationId === null || $user->email === null) {
            return OrderCommunication::query()->whereRaw('1 = 0');
        }

        return OrderCommunication::query()
            ->where('organization_id', $organizationId)
            ->where('channel', CommunicationChannel::INTERNAL_NOTIFICATION->value)
            ->where('recipient_email', $user->email);
    }

    /**
     * Les envois externes qui ont échoué.
     *
     * Ils ne sont adressés à personne dans l'application : c'est l'organisation
     * entière qui doit les reprendre. Ils n'ont donc pas d'état de lecture, et
     * ne comptent pas dans la pastille — un compteur qu'aucun geste ne fait
     * baisser cesse d'être lu au bout d'une journée.
     *
     * @return Builder<OrderCommunication>
     */
    public function externalFailures(?string $organizationId): Builder
    {
        if ($organizationId === null) {
            return OrderCommunication::query()->whereRaw('1 = 0');
        }

        return OrderCommunication::query()
            ->where('organization_id', $organizationId)
            ->where('channel', '!=', CommunicationChannel::INTERNAL_NOTIFICATION->value)
            ->where('status', CommunicationStatus::FAILED->value);
    }

    public function unreadCount(User $user, ?string $organizationId): int
    {
        return $this->internalFor($user, $organizationId)->whereNull('read_at')->count();
    }

    /**
     * Les dix dernières de chaque moitié, sans leur contenu.
     *
     * `body` est un `longText` qui porte le message rendu. Un panneau déroulant
     * n'en a pas l'usage — il montre l'objet et la date — et le transporter
     * mettrait dix messages complets dans une réponse ouverte à chaque page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function take(Builder $query): array
    {
        return $query
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get(['id', 'order_id', 'subject', 'recipient_name', 'channel', 'status', 'read_at', 'created_at'])
            ->map(static fn (OrderCommunication $notification): array => [
                'id' => $notification->getKey(),
                'title' => $notification->getAttribute('subject') ?? $notification->getAttribute('recipient_name'),
                'recipient' => $notification->getAttribute('recipient_name'),
                'channel' => $notification->getAttribute('channel')?->value,
                'status' => $notification->getAttribute('status')?->value,
                'isRead' => $notification->getAttribute('read_at') !== null,
                'date' => $notification->getAttribute('created_at')?->toIso8601String(),
                // La commande est le seul écran où la communication se lit en
                // entier. Une notification sans commande n'ouvre rien plutôt que
                // de mener à une page introuvable.
                'route' => $notification->getAttribute('order_id') === null
                    ? null
                    : '/orders/'.$notification->getAttribute('order_id'),
            ])
            ->all();
    }
}
