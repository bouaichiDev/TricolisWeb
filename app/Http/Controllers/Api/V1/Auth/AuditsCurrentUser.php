<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;

/**
 * Journalise une action réalisée par l'utilisateur sur son propre compte, en la
 * rattachant à son organisation principale.
 */
trait AuditsCurrentUser
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    protected function auditForUser(Request $request, string $action, ?array $oldValues = null, ?array $newValues = null): void
    {
        /** @var User $user */
        $user = $request->user();

        $membership = $user->organizationUsers()->where('is_primary', true)->first()
            ?? $user->organizationUsers()->first();

        if ($membership !== null) {
            $this->audit($request, $membership->organization_id, $action, $user, $oldValues, $newValues);
        }
    }
}
