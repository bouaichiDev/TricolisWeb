<?php

declare(strict_types=1);

namespace App\Modules\Providers\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Providers\DTOs\CreateProviderData;
use App\Modules\Providers\Models\Provider;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Crée un fournisseur dans l'organisation active.
 */
final readonly class CreateProviderAction
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(CreateProviderData $data, AuditContext $context): Provider
    {
        return DB::transaction(function () use ($data, $context): Provider {
            $provider = Provider::create($data->toAttributes($context->organizationId));

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'provider.created',
                $provider,
                null,
                $provider->only(['code', 'name', 'provider_type', 'status']),
                null,
                $context->ipAddress,
            );

            return $provider;
        });
    }
}
