<?php

declare(strict_types=1);

namespace App\Shared\Support;

use App\Modules\Identity\Models\User;

/**
 * Qui agit, depuis où, et dans quelle organisation.
 *
 * Permet aux Actions métier de produire un audit complet sans recevoir de
 * `Request` : elles restent testables hors contexte HTTP.
 */
final readonly class AuditContext
{
    public function __construct(
        public string $organizationId,
        public ?User $user = null,
        public ?string $ipAddress = null,
    ) {}
}
