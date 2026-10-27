<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\Claims\Actions\UpdateClaimAction;
use App\Modules\Claims\DTOs\UpdateClaimData;
use App\Modules\Claims\Models\Claim;
use App\Modules\CustomerPortal\Exceptions\CustomerClaimException;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Shared\Support\AuditContext;
use Illuminate\Http\Request;

/**
 * Modification d'une réclamation par le client (§11).
 *
 * Seuls titre, description et type — ce que le client a lui-même écrit — et
 * seulement tant que la réclamation est ouverte (`closed_at` nul). Statut,
 * cause, décision, suivi, résultat, coût, responsable et clôture restent au
 * transporteur. L'écriture passe par `UpdateClaimAction` : même audit
 * `claim.updated` que l'interne.
 */
final readonly class UpdateCustomerClaim
{
    private const array EDITABLE = ['title', 'description', 'claimType'];

    public function __construct(
        private CurrentCustomerContext $context,
        private UpdateClaimAction $update,
        private CustomerPortalAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(Claim $claim, array $input, Request $request): Claim
    {
        if ($claim->isClosed()) {
            throw CustomerClaimException::notEditable();
        }

        $customer = $this->context->customer();
        $user = $this->context->user();
        $changes = array_intersect_key($input, array_flip(self::EDITABLE));
        $before = $claim->only(['title', 'description', 'claim_type']);

        $updated = $this->update->execute(
            $claim,
            UpdateClaimData::fromValidated($changes),
            new AuditContext($customer->organization_id, $user, $request->ip()),
        );

        if ($before !== $updated->only(['title', 'description', 'claim_type'])) {
            $this->audit->record($customer, $user, 'claim_updated', $updated, null, [
                'claimId' => $updated->id,
                'fields' => array_keys($changes),
            ], $request->ip());
        }

        return $updated;
    }
}
