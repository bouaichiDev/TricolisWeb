<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Change le statut d'une commande en respectant le workflow.
 *
 * Le changement libre de statut est interdit : la transition doit être déclarée
 * par `OrderStatus`, le statut cible doit être assignable manuellement à ce
 * stade du projet, et un motif est exigé pour l'annulation.
 *
 * L'historique n'est pas stocké dans une table dédiée — le diagramme n'en
 * prévoit pas. Il est reconstitué depuis le journal d'audit, qui enregistre
 * chaque transition avec son ancien et son nouveau statut.
 */
final readonly class ChangeOrderStatus
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(
        Order $order,
        OrderStatus $target,
        ?User $user,
        ?string $reasonCode = null,
        ?string $reasonText = null,
        ?Request $request = null,
    ): Order {
        $current = $order->status;

        if (! $target->isManuallyAssignable()) {
            throw InvalidOrderTransition::notManuallyAssignable($target);
        }

        if (! $current->canTransitionTo($target)) {
            throw InvalidOrderTransition::between($current, $target);
        }

        if ($target->requiresReason() && blank($reasonText) && blank($reasonCode)) {
            throw InvalidOrderTransition::reasonRequired($target);
        }

        return DB::transaction(function () use ($order, $current, $target, $user, $reasonCode, $reasonText, $request): Order {
            $order->forceFill([
                'status' => $target,
                'updated_by' => $user?->id,
            ])->save();

            $this->audit->execute(
                $order->organization_id,
                $user,
                'status_changed',
                $order,
                ['status' => $current->value],
                array_filter([
                    'status' => $target->value,
                    'reason_code' => $reasonCode,
                    'reason_text' => $reasonText,
                ], static fn ($value): bool => $value !== null),
                $request,
            );

            return $order;
        });
    }
}
