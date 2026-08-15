<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Communications;

use App\Http\Controllers\Api\V1\Concerns\BuildsAuditContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Communications\OrderCommunicationDetailResource;
use App\Modules\Communications\Actions\CancelOrderCommunicationAction;
use App\Modules\Communications\Actions\QueueOrderCommunicationAction;
use App\Modules\Communications\Actions\RetryOrderCommunicationAction;
use App\Modules\Communications\Exceptions\CommunicationNotEditable;
use App\Modules\Communications\Models\OrderCommunication;
use App\Shared\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Transitions d'état d'une communication.
 *
 * Séparées du CRUD parce qu'elles n'en sont pas : mettre en file déclenche un
 * envoi vers un tiers, annuler l'interrompt, relancer le refait. Chacune a sa
 * permission — pouvoir corriger un brouillon ne doit pas suffire à l'expédier.
 */
class OrderCommunicationStateController extends Controller
{
    use BuildsAuditContext;
    use ResolvesCommunicationScope;

    /**
     * Mettre une communication en file d'envoi.
     *
     * Permission requise : `order_communications.queue`. Refusé en 409 si la
     * communication est déjà partie, annulée ou en échec — une relance passe
     * par `retry`.
     */
    public function queue(
        Request $request,
        OrderCommunication $orderCommunication,
        QueueOrderCommunicationAction $action,
    ): JsonResponse {
        return $this->transition(
            $request,
            $orderCommunication,
            'queue',
            fn (string $organizationId): OrderCommunication => $action->execute(
                $orderCommunication,
                $this->auditContext($request, $organizationId),
            ),
        );
    }

    /**
     * Annuler une communication non partie.
     *
     * Permission requise : `order_communications.cancel`. Possible en brouillon,
     * programmée ou en file ; refusé en 409 au-delà.
     */
    public function cancel(
        Request $request,
        OrderCommunication $orderCommunication,
        CancelOrderCommunicationAction $action,
    ): JsonResponse {
        return $this->transition(
            $request,
            $orderCommunication,
            'cancel',
            fn (string $organizationId): OrderCommunication => $action->execute(
                $orderCommunication,
                $this->auditContext($request, $organizationId),
            ),
        );
    }

    /**
     * Relancer une communication échouée.
     *
     * Permission requise : `order_communications.retry`. Seul le statut `failed`
     * l'autorise : relancer une communication envoyée la dupliquerait.
     */
    public function retry(
        Request $request,
        OrderCommunication $orderCommunication,
        RetryOrderCommunicationAction $action,
    ): JsonResponse {
        return $this->transition(
            $request,
            $orderCommunication,
            'retry',
            fn (string $organizationId): OrderCommunication => $action->execute(
                $orderCommunication,
                $this->auditContext($request, $organizationId),
            ),
        );
    }

    private function transition(
        Request $request,
        OrderCommunication $communication,
        string $ability,
        Closure $apply,
    ): JsonResponse {
        $organizationId = $this->guardCommunication($communication);
        $this->authorize($ability, $communication);

        try {
            $updated = $apply($organizationId);
        } catch (CommunicationNotEditable $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        return ApiResponse::ok(new OrderCommunicationDetailResource($updated));
    }
}
