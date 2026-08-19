<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use App\Modules\Orders\Models\Order;
use App\Modules\Statuses\Services\StatusMachine;
use App\Shared\Database\MorphMap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Change le statut d'une commande en respectant le workflow.
 *
 * Le changement libre de statut est interdit : la transition doit être déclarée
 * dans `status_transitions`, elle doit être posable à la main, et le statut
 * cible peut exiger un motif.
 *
 * **La règle vient du référentiel, plus de l'énumération.** C'est
 * l'administrateur plateforme qui dessine le cycle de vie ; laisser une seconde
 * définition dans le code la ferait diverger au premier statut ajouté.
 *
 * L'historique n'est pas stocké dans une table dédiée — le diagramme n'en
 * prévoit pas. Il est reconstitué depuis le journal d'audit, qui enregistre
 * chaque transition avec son ancien et son nouveau statut.
 */
final readonly class ChangeOrderStatus
{
    public function __construct(
        private WriteAuditLog $audit,
        private StatusMachine $machine,
    ) {}

    public function execute(
        Order $order,
        OrderStatus $target,
        ?User $user,
        ?string $reasonCode = null,
        ?string $reasonText = null,
        ?Request $request = null,
    ): Order {
        $current = $order->status;

        if (! $this->machine->allows(MorphMap::ORDER, $current?->value, $target->value)) {
            throw InvalidOrderTransition::between($current, $target, array_map(
                static fn ($status): string => $status->code,
                $this->machine->transitionsFrom(MorphMap::ORDER, $current?->value),
            ));
        }

        if (! $this->machine->allowsManually(MorphMap::ORDER, $current?->value, $target->value)) {
            throw InvalidOrderTransition::notManuallyAssignable($target);
        }

        if ($this->machine->requiresReason(MorphMap::ORDER, $target->value)
            && blank($reasonText) && blank($reasonCode)) {
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
