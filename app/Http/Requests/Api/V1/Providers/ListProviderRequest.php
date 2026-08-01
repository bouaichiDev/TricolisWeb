<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Providers;

use App\Shared\Http\Requests\ListRequest;

/**
 * Filtres propres aux fournisseurs.
 *
 * `organizationId` n'est pas accepté : la liste est toujours restreinte à
 * l'organisation active. Le §8 le réserve à un profil multi-organisation, qui
 * n'existe pas dans le modèle de sécurité actuel — un rattachement donne accès
 * à une organisation à la fois, via l'en-tête.
 */
class ListProviderRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'providerType' => ['sometimes', 'string', 'max:64'],
            'legacyId' => ['sometimes', 'integer', 'min:0'],
        ]);
    }
}
