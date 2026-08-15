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
 * Le fournisseur est revérifié ici, et pas seulement dans le Form Request :
 * l'Action doit rester sûre appelée directement, depuis un import par exemple.
 *
 * L'`organization_id` du chauffeur est celui du fournisseur retenu, jamais une
 * valeur fournie par l'appelant : le diagramme porte les deux, ils ne doivent
 * pas pouvoir diverger.
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

        return DB::transaction(function () use ($data, $provider, $context): Driver {
            $driver = Driver::create($data->toAttributes($provider->organization_id));

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'driver.created',
                $driver,
                null,
                $driver->only(['provider_id', 'code', 'name', 'status', 'address_id', 'contact_id']),
                null,
                $context->ipAddress,
            );

            return $driver;
        });
    }
}
