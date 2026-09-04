<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Communications;

use App\Http\Controllers\Controller;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Communications\Services\UserNotifications;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La cloche du bandeau supérieur.
 *
 * L'en-tête portait cette place vide depuis la Phase 10, et le disait :
 * « aucun endpoint ne les alimente ». En voici un — bâti sur ce que le domaine
 * porte déjà, sans table nouvelle. Voir `UserNotifications`.
 *
 * **Hors du middleware `organization`.** La cloche est rendue sur chaque page,
 * y compris pour un compte plateforme qui n'agit dans aucune organisation :
 * exiger l'en-tête lui rendrait une erreur là où la réponse juste est « rien à
 * signaler ».
 *
 * Les deux moitiés ne se protègent pas de la même façon, et c'est le point :
 *
 * - **les internes m'appartiennent** — elles portent mon adresse, elles me sont
 *   adressées, et exiger `order_communications.view` pour lire ce qu'on m'écrit
 *   aurait été absurde ;
 * - **les externes appartiennent à l'organisation** — ce sont les envois
 *   qu'elle a faits à ses clients, et les lire demande la permission qui ouvre
 *   leur historique.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly UserNotifications $notifications) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $organizationId = $this->organizationId();

        $seesExternal = $user->can('viewAny', [OrderCommunication::class, $organizationId]);

        return ApiResponse::ok([
            'unread' => $this->notifications->unreadCount($user, $organizationId),
            'internal' => $this->notifications->take(
                $this->notifications->internalFor($user, $organizationId)
            ),
            // Une liste vide plutôt qu'une clé absente : l'écran n'a pas à
            // distinguer « pas le droit » de « rien à montrer », et lui dire
            // lequel des deux renseignerait sur ce qui existe ailleurs.
            'external' => $seesExternal
                ? $this->notifications->take($this->notifications->externalFailures($organizationId))
                : [],
        ]);
    }

    /**
     * Marquer une notification comme lue.
     *
     * `markAsRead` et non `update` : lire ce qu'on m'écrit n'est pas modifier une
     * communication. La première n'appartient qu'à son destinataire, la seconde
     * demande `order_communications.update` — et un porteur de cette permission
     * n'a pas à marquer lues les notifications de quelqu'un d'autre.
     */
    public function markAsRead(Request $request, OrderCommunication $orderCommunication): JsonResponse
    {
        $this->authorize('markAsRead', $orderCommunication);

        // `read_at` n'est écrit qu'une fois : le relire ne doit pas déplacer la
        // date à laquelle on l'a vue pour la première fois.
        if ($orderCommunication->read_at === null) {
            $orderCommunication->forceFill(['read_at' => now()])->save();
        }

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::ok(['unread' => $this->notifications->unreadCount($user, $this->organizationId())]);
    }

    /**
     * Tout marquer comme lu.
     *
     * Une seule écriture, et bornée aux miennes : la requête ne prend aucun
     * identifiant, et ne peut donc pas déborder sur celles d'un voisin.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->notifications
            ->internalFor($user, $this->organizationId())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::ok(['unread' => 0]);
    }
}
