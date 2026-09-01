<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Templates;

use App\Modules\Templates\Models\Template;

/**
 * Vérifie qu'un modèle relève de l'organisation active.
 *
 * Un identifiant valide hors périmètre renvoie **404**, jamais 403 : l'existence
 * d'une ressource appartenant à un autre transporteur ne se révèle pas. C'est la
 * convention des Phases 4 à 8.
 */
trait ResolvesTemplateScope
{
    protected function guardTemplate(Template $template): string
    {
        $organizationId = $this->requireOrganizationId();
        abort_unless($template->organization_id === $organizationId, 404, 'Modèle introuvable.');

        return $organizationId;
    }
}
