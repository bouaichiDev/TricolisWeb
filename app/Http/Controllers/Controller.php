<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Identity\Models\User;
use App\Shared\Organizations\CurrentOrganizationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Organisation active, ou `null` lorsque l'appelant n'en désigne aucune.
     *
     * Utile aux routes qui servent les deux portées : un compte plateforme
     * n'agit dans aucune organisation, et exiger l'en-tête lui interdirait
     * l'accès. Les routes qui *travaillent* dans une organisation continuent
     * d'utiliser `requireOrganizationId()`, qui vérifie aussi l'appartenance.
     */
    protected function organizationId(): ?string
    {
        /** @var CurrentOrganizationContext $context */
        $context = Container::getInstance()->make(CurrentOrganizationContext::class);

        return $context->hasOrganizationAccess() ? $context->getOrganizationId() : null;
    }

    /**
     * @throws AuthorizationException
     */
    protected function requireOrganizationId(): string
    {
        /** @var CurrentOrganizationContext $context */
        $context = Container::getInstance()->make(CurrentOrganizationContext::class);

        $organizationId = $context->getOrganizationId();

        if ($organizationId === null) {
            throw new AuthorizationException('L\'en-tête X-Organization-Id est requis.');
        }

        if (! $context->hasOrganizationAccess()) {
            throw new AuthorizationException('Vous n\'avez pas accès à cette organisation.');
        }

        return $organizationId;
    }

    protected function audit(Request $request, string $organizationId, string $action, Model $entity, ?array $oldValues = null, ?array $newValues = null): void
    {
        /** @var WriteAuditLog $writer */
        $writer = Container::getInstance()->make(WriteAuditLog::class);
        /** @var User|null $user */
        $user = $request->user();
        $writer->execute($organizationId, $user, $action, $entity, $oldValues, $newValues, $request);
    }
}
