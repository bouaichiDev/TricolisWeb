<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Services;

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Communications\Enums\RecipientRole;
use App\Modules\Communications\Models\OrderCommunication;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Les communications qu'un membre du portail a reçues.
 *
 * Aucun rôle destinataire « fournisseur » n'existe : le seul lien entre une
 * communication et une personne est son **adresse** — le mécanisme de
 * `UserNotifications`. On ne montre donc que :
 *
 * - les **e-mails** (le seul canal réellement envoyé à cette adresse : un SMS
 *   part vers un téléphone, même si la fiche du contact porte l'adresse) ;
 * - partis vers l'adresse du membre, comparée **à l'octet près** après mise en
 *   minuscules (la collation par défaut confondrait `jose@` et `josé@`) ;
 * - dans l'organisation de son fournisseur, **depuis son rattachement** : ce
 *   que la même adresse a reçu avant, comme contact d'un client par exemple,
 *   ne regarde pas le portail ;
 * - jamais adressés en tant que collaborateur interne ;
 * - réellement partis (envoyé, remis, lu).
 */
final readonly class ProviderCommunications
{
    public const array DELIVERED = [CommunicationStatus::SENT, CommunicationStatus::DELIVERED, CommunicationStatus::READ];

    public function __construct(private CurrentProviderContext $context) {}

    /**
     * @return Builder<OrderCommunication>
     */
    public function query(): Builder
    {
        $membership = $this->context->membership();
        $since = $membership->activated_at ?? $membership->invited_at ?? $membership->created_at;

        return OrderCommunication::query()
            ->where('organization_id', $this->context->organizationId())
            ->whereRaw('LOWER(recipient_email) COLLATE utf8mb4_bin = ?', [mb_strtolower(trim((string) $this->context->user()->email))])
            ->where('channel', CommunicationChannel::EMAIL->value)
            ->where(static fn (Builder $role) => $role->whereNull('recipient_role')->orWhere('recipient_role', '!=', RecipientRole::INTERNAL_USER->value))
            ->whereIn('status', array_map(static fn (CommunicationStatus $status): string => $status->value, self::DELIVERED))
            ->when($since !== null, static fn (Builder $query) => $query->where('sent_at', '>=', $since));
    }

    public function find(string $id): OrderCommunication
    {
        return $this->query()->whereKey($id)->first()
            ?? throw new NotFoundHttpException('Communication introuvable.');
    }
}
