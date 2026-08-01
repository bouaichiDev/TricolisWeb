<?php

declare(strict_types=1);

namespace App\Modules\Drivers\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Drivers\Models\Driver;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Supprime un chauffeur.
 *
 * Le cahier des charges demande de refuser la suppression d'un chauffeur
 * référencé par une tournée. Le module Tours n'existe pas encore : le contrôle
 * sera ajouté avec la phase Planification, et il devra l'être avant que les
 * tournées ne soient exploitées.
 */
final readonly class DeleteDriverAction
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(Driver $driver, AuditContext $context): void
    {
        DB::transaction(function () use ($driver, $context): void {
            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'driver.deleted',
                $driver,
                $driver->only(['provider_id', 'code', 'first_name', 'last_name', 'status']),
                null,
                null,
                $context->ipAddress,
            );

            $driver->delete();
        });
    }
}
