<?php

declare(strict_types=1);

namespace App\Modules\Providers\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Providers\Exceptions\ProviderStillInUse;
use App\Modules\Providers\Models\Provider;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Supprime un fournisseur, à condition qu'il ne porte plus de ressource.
 *
 * Aucune cascade : supprimer un fournisseur ne doit jamais emporter
 * silencieusement ses chauffeurs et ses véhicules.
 */
final readonly class DeleteProviderAction
{
    public function __construct(private WriteAuditLog $audit) {}

    /**
     * @throws ProviderStillInUse
     */
    public function execute(Provider $provider, AuditContext $context): void
    {
        if ($provider->hasResources()) {
            throw ProviderStillInUse::hasDriversOrVehicles();
        }

        DB::transaction(function () use ($provider, $context): void {
            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'provider.deleted',
                $provider,
                $provider->only(['code', 'name', 'provider_type', 'status']),
                null,
                null,
                $context->ipAddress,
            );

            $provider->delete();
        });
    }
}
