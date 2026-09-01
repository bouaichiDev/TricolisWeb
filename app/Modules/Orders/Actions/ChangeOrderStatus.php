<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Exceptions\InvalidOrderTransition;
use App\Modules\Orders\Models\Order;
use App\Modules\Statuses\Services\StatusMachine;
use App\Modules\Stock\Actions\ConsumeOrderStock;
use App\Shared\Database\MorphMap;
use App\Shared\Support\AuditContext;
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
 *
 * **La confirmation sort la marchandise du stock.** C'est le seul effet de bord
 * d'un changement de statut, et il est dans la même transaction : une commande
 * confirmée dont le stock n'aurait pas bougé — ou l'inverse — laisserait un
 * dépôt qui ment. Les lignes hors catalogue et les articles non entreposés sont
 * ignorés ; il ne s'agit pas d'une erreur.
 */
final readonly class ChangeOrderStatus
{
    public function __construct(
        private WriteAuditLog $audit,
        private StatusMachine $machine,
        private ConsumeOrderStock $stock,
    ) {}

    public function execute(
        Order $order,
        OrderStatus $target,
        ?User $user,
        ?string $reasonCode = null,
        ?string $reasonText = null,
        ?Request $request = null,
        /** @var array<string, string> orderLineId => stockLocationId */
        array $stockLocations = [],
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

        $changed = DB::transaction(function () use ($order, $current, $target, $user, $reasonCode, $reasonText, $request, $stockLocations): Order {
            if ($target === OrderStatus::CONFIRMED) {
                $this->stock->execute(
                    $order,
                    $stockLocations,
                    new AuditContext($order->organization_id, $user, $request?->ip()),
                    now()->toDateTimeString(),
                );
            }

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

        // Apres le commit, jamais pendant : un message parti pendant la
        // transaction survivrait a son rollback, et le client recevrait
        // l'annulation d'une commande qui ne l'est pas.
        OrderStatusChanged::dispatch(
            $changed,
            $current,
            $target,
            new AuditContext($changed->organization_id, $user, $request?->ip()),
        );

        return $changed;
    }
}
