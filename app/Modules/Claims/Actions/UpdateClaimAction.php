<?php

declare(strict_types=1);

namespace App\Modules\Claims\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Claims\DTOs\UpdateClaimData;
use App\Modules\Claims\Models\Claim;
use App\Modules\Claims\Services\ClaimScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Modifie une réclamation, y compris pour la clôturer.
 *
 * Il n'y a pas d'Action de clôture séparée : le §19 ne la justifie que « si elle
 * réutilise les champs existants », et clôturer se réduit à renseigner
 * `closedAt`, `decision` et `result` — exactement ce que fait `PATCH`. Une
 * seconde Action dupliquerait les mêmes contrôles pour le même effet.
 *
 * La clôture est en revanche **auditée séparément** sous `claim.closed` : c'est
 * un événement de dossier, pas une correction de saisie.
 */
final readonly class UpdateClaimAction
{
    public function __construct(
        private ClaimScopeGuard $guard,
        private WriteAuditLog $audit,
    ) {}

    public function execute(Claim $claim, UpdateClaimData $data, AuditContext $context): Claim
    {
        $attributes = $data->attributes->all();

        if ($attributes === []) {
            return $claim;
        }

        $this->assertReferences($claim, $attributes, $context->organizationId);

        return DB::transaction(function () use ($claim, $attributes, $context): Claim {
            $wasOpen = ! $claim->isClosed();
            $before = $claim->only(array_keys($attributes));
            $claim->update($attributes);
            $after = $claim->fresh()->only(array_keys($attributes));

            if ($before === $after) {
                return $claim->fresh();
            }

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'claim.updated',
                $claim,
                $before,
                $after,
                null,
                $context->ipAddress,
            );

            if ($wasOpen && $claim->fresh()->isClosed()) {
                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'claim.closed',
                    $claim,
                    null,
                    ['closed_at' => $claim->fresh()->closed_at?->toIso8601String()],
                    null,
                    $context->ipAddress,
                );
            }

            return $claim->fresh();
        });
    }

    /**
     * Les références modifiées sont recontrôlées contre le client de la
     * réclamation, jamais contre celui du payload : le client n'est pas
     * modifiable.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function assertReferences(Claim $claim, array $attributes, string $organizationId): void
    {
        $customer = $claim->customer;

        $orderId = array_key_exists('order_id', $attributes) ? $attributes['order_id'] : $claim->order_id;
        $order = $orderId !== null ? $this->guard->order($orderId, $customer) : null;

        $serviceId = array_key_exists('order_service_id', $attributes)
            ? $attributes['order_service_id']
            : $claim->order_service_id;

        if ($serviceId !== null) {
            $this->guard->orderService($serviceId, $order, $customer);
        }

        if (($attributes['tour_id'] ?? null) !== null) {
            $this->guard->tour($attributes['tour_id'], $organizationId);
        }

        if (($attributes['responsible_user_id'] ?? null) !== null) {
            $this->guard->user($attributes['responsible_user_id'], $organizationId);
        }

        if (($attributes['closed_at'] ?? null) !== null) {
            $this->guard->assertClosedAtIsCoherent($attributes['closed_at'], $claim->created_at);
        }
    }
}
