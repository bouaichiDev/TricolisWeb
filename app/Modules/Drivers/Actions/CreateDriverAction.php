<?php

declare(strict_types=1);

namespace App\Modules\Drivers\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Drivers\DTOs\CreateDriverData;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Providers\Services\ProviderScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Crée un chauffeur chez un fournisseur de l'organisation active.
 *
 * Le fournisseur et le compte utilisateur éventuel sont revérifiés ici, et pas
 * seulement dans le Form Request : l'Action doit rester sûre appelée
 * directement, depuis un import par exemple.
 */
final readonly class CreateDriverAction
{
    public function __construct(
        private ProviderScopeGuard $guard,
        private WriteAuditLog $audit,
    ) {}

    public function execute(CreateDriverData $data, AuditContext $context): Driver
    {
        $provider = $this->guard->provider($data->providerId, $context->organizationId);

        if ($data->userId !== null) {
            $this->guard->user($data->userId, $context->organizationId);
        }

        return DB::transaction(function () use ($data, $provider, $context): Driver {
            $driver = $provider->drivers()->create($data->toAttributes());

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'driver.created',
                $driver,
                null,
                $driver->only(['provider_id', 'code', 'first_name', 'last_name', 'status']),
                null,
                $context->ipAddress,
            );

            return $driver;
        });
    }
}
