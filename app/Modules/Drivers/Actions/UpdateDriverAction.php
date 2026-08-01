<?php

declare(strict_types=1);

namespace App\Modules\Drivers\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Drivers\DTOs\UpdateDriverData;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Providers\Services\ProviderScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Modifie un chauffeur.
 *
 * Changer de fournisseur reste possible, mais uniquement vers un fournisseur de
 * la même organisation : un chauffeur ne peut pas être déplacé hors périmètre.
 */
final readonly class UpdateDriverAction
{
    public function __construct(
        private ProviderScopeGuard $guard,
        private WriteAuditLog $audit,
    ) {}

    public function execute(Driver $driver, UpdateDriverData $data, AuditContext $context): Driver
    {
        $attributes = $data->attributes->all();

        if ($attributes === []) {
            return $driver;
        }

        if ($data->attributes->has('provider_id')) {
            $this->guard->provider($data->attributes->get('provider_id'), $context->organizationId);
        }

        return DB::transaction(function () use ($driver, $attributes, $context): Driver {
            $before = $driver->only(array_keys($attributes));
            $driver->update($attributes);
            $after = $driver->fresh()->only(array_keys($attributes));

            if ($before !== $after) {
                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'driver.updated',
                    $driver,
                    $before,
                    $after,
                    null,
                    $context->ipAddress,
                );
            }

            return $driver->fresh();
        });
    }
}
