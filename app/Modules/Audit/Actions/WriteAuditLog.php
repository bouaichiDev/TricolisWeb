<?php

declare(strict_types=1);

namespace App\Modules\Audit\Actions;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class WriteAuditLog
{
    private const array SENSITIVE_KEYS = ['password', 'password_hash', 'password_confirmation', 'token', 'api_key', 'secret'];

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
        if ($values === null) {
            return null;
        }
        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->sanitize($value);
            }
        }

        return $values;
    }
}
