<?php

declare(strict_types=1);

namespace App\Modules\Claims\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Claims\DTOs\CreateClaimData;
use App\Modules\Claims\Models\Claim;
use App\Modules\Claims\Services\ClaimScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Crée une réclamation.
 *
 * L'organisation est prise sur le client, ce qu'exige le §18
 * (`Claim.organization_id == Customer.organization_id`). `created_at` est posé
 * ici : le modèle a `$timestamps = false`, le diagramme ne déclarant pas
 * d'`updatedAt`.
 */
final readonly class CreateClaimAction
{
    public function __construct(
        private ClaimScopeGuard $guard,
        private WriteAuditLog $audit,
    ) {}

    public function execute(CreateClaimData $data, AuditContext $context, string $now): Claim
    {
        $customer = $this->guard->customer($data->customerId, $context->organizationId);

        $order = $data->orderId !== null
            ? $this->guard->order($data->orderId, $customer)
            : null;

        if ($data->orderServiceId !== null) {
            $this->guard->orderService($data->orderServiceId, $order, $customer);
        }

        if ($data->tourId !== null) {
            $this->guard->tour($data->tourId, $context->organizationId);
        }

        if ($data->responsibleUserId !== null) {
            $this->guard->user($data->responsibleUserId, $context->organizationId);
        }

        return DB::transaction(function () use ($data, $customer, $context, $now): Claim {
            $claim = Claim::create(
                $data->toAttributes($customer->organization_id, $context->user?->id, $now),
            );

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'claim.created',
                $claim,
                null,
                $claim->only(['customer_id', 'title', 'claim_type', 'status']),
                null,
                $context->ipAddress,
            );

            return $claim;
        });
    }
}
