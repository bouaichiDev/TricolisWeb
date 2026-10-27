<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\Claims\Actions\CreateClaimAction;
use App\Modules\Claims\DTOs\CreateClaimData;
use App\Modules\Claims\Models\Claim;
use App\Modules\CustomerPortal\Exceptions\CustomerClaimException;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\Statuses\Services\DefaultStatuses;
use App\Shared\Database\MorphMap;
use App\Shared\Support\AuditContext;
use Illuminate\Http\Request;

/**
 * Création d'une réclamation depuis le portail (§6).
 *
 * Le service métier est **celui de l'interne** (`CreateClaimAction`) : mêmes
 * contrôles (`ClaimScopeGuard` — la commande appartient au client, le service
 * à la commande), même transaction, même audit `claim.created`. Le portail ne
 * fait qu'imposer ce que le client ne choisit pas :
 *
 * - `customerId` et l'organisation, lus dans le jeton ;
 * - `createdBy`, le membre connecté ;
 * - le statut, **celui que le référentiel désigne par défaut** pour la source
 *   `claim`. Sans statut par défaut configuré, la création est refusée
 *   proprement : le portail n'invente aucun statut.
 */
final readonly class CreateCustomerClaim
{
    public function __construct(
        private CurrentCustomerContext $context,
        private CreateClaimAction $create,
        private DefaultStatuses $defaults,
        private CustomerPortalAudit $audit,
    ) {}

    /**
     * @param  array{title: string, claimType: string, description?: string|null, orderId?: string|null, orderServiceId?: string|null}  $input
     */
    public function execute(array $input, Request $request): Claim
    {
        if ($this->defaults->chosen(MorphMap::CLAIM) === null && DefaultStatuses::columnDefault('claims') === null) {
            throw CustomerClaimException::statusNotConfigured();
        }

        $customer = $this->context->customer();
        $user = $this->context->user();

        $claim = $this->create->execute(new CreateClaimData(
            customerId: $customer->id,
            title: $input['title'],
            claimType: $input['claimType'],
            orderId: $input['orderId'] ?? null,
            orderServiceId: $input['orderServiceId'] ?? null,
            description: $input['description'] ?? null,
        ), new AuditContext($customer->organization_id, $user, $request->ip()), now()->toDateTimeString());

        $this->audit->record($customer, $user, 'claim_created', $claim, null, [
            'claimId' => $claim->id,
            'orderId' => $claim->order_id,
            'orderServiceId' => $claim->order_service_id,
        ], $request->ip());

        return $claim;
    }
}
