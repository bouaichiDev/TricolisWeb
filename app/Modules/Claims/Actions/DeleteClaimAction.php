<?php

declare(strict_types=1);

namespace App\Modules\Claims\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Claims\Exceptions\ClaimNotDeletable;
use App\Modules\Claims\Models\Claim;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Supprime une réclamation ouverte.
 *
 * Le §29 n'autorise la suppression que « si le statut ou les règles existantes
 * l'autorisent ». Aucun workflow de statut n'existe, et le §16 interdit
 * d'inventer les valeurs de `status` : les interpréter reviendrait à décider
 * lesquelles sont supprimables, ce que personne n'a arrêté.
 *
 * Le seul critère objectif porté par le modèle est `closedAt`. Une réclamation
 * clôturée est un dossier tranché, qui documente une décision et parfois un
 * coût : sa suppression est refusée en 409.
 */
final readonly class DeleteClaimAction
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(Claim $claim, AuditContext $context): void
    {
        if ($claim->isClosed()) {
            throw ClaimNotDeletable::alreadyClosed();
        }

        DB::transaction(function () use ($claim, $context): void {
            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'claim.deleted',
                $claim,
                $claim->only(['customer_id', 'title', 'claim_type', 'status']),
                null,
                null,
                $context->ipAddress,
            );

            $claim->delete();
        });
    }
}
