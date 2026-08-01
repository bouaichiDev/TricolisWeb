<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Modules\Identity\Models\User;
use App\Shared\Support\AuditContext;
use Illuminate\Http\Request;

/**
 * Construit le contexte d'audit à partir de la requête HTTP.
 *
 * C'est le seul point où la couche HTTP est traduite pour les Actions :
 * celles-ci reçoivent une organisation, un utilisateur et une adresse IP, et
 * restent appelables hors requête.
 */
trait BuildsAuditContext
{
    protected function auditContext(Request $request, string $organizationId): AuditContext
    {
        /** @var User|null $user */
        $user = $request->user();

        return new AuditContext($organizationId, $user, $request->ip());
    }
}
