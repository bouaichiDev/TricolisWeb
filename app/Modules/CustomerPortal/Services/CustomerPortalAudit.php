<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Le module Audit existant, appliqué au portail client.
 *
 * `audit_logs` est rattaché à une organisation : on y écrit celle du
 * transporteur qui gère le client, pour que ses administrateurs retrouvent
 * ces événements dans leur journal. L'identifiant du client accompagne chaque
 * entrée, et les actions sont préfixées `customer_portal.` pour qu'aucune ne
 * se confonde avec une action interne.
 */
final readonly class CustomerPortalAudit
{
    public const string PREFIX = 'customer_portal.';

    public function __construct(private WriteAuditLog $writer) {}

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(
        Customer $customer,
        ?User $actor,
        string $action,
        Model $entity,
        ?array $oldValues = null,
        array $newValues = [],
        ?string $ipAddress = null,
    ): void {
        $this->writer->execute(
            $customer->organization_id,
            $actor,
            self::PREFIX.$action,
            $entity,
            $oldValues,
            ['customerId' => $customer->id, ...$newValues],
            null,
            $ipAddress,
        );
    }
}
