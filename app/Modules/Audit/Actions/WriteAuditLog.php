<?php

declare(strict_types=1);

namespace App\Modules\Audit\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Shared\Security\SensitiveData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Écrit une entrée d'audit — le seul chemin vers `audit_logs`.
 *
 * Les valeurs passent par `SensitiveData` avant écriture : un secret ne
 * devient jamais une donnée d'audit, quel que soit l'appelant (Phase 7).
 */
final class WriteAuditLog
{
    /**
     * `$ipAddress` permet aux Actions métier de journaliser sans dépendre de
     * la couche HTTP : elles reçoivent une adresse, pas une Request.
     */
    public function execute(string $organizationId, ?User $user, string $action, Model $entity, ?array $oldValues = null, ?array $newValues = null, ?Request $request = null, ?string $ipAddress = null): AuditLog
    {
        return AuditLog::create([
            'organization_id' => $organizationId,
            'user_id' => $user?->id,
            'action' => $action,
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => (string) $entity->getKey(),
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'ip_address' => $ipAddress ?? $request?->ip(),
            'created_at' => now(),
        ]);
    }

    private function sanitize(?array $values): ?array
    {
        return $values === null ? null : SensitiveData::redact($values);
    }
}
