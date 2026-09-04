<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Templates;

use App\Shared\Http\Requests\ListRequest;

/**
 * Filtres de la liste des modèles.
 *
 * `customerId` accepte la chaîne `global` en plus d'un identifiant : c'est ce
 * qui permet à l'écran de n'afficher que les modèles du transporteur, sans quoi
 * « aucun client » et « tous les clients » se demanderaient de la même façon.
 */
class ListTemplateRequest extends ListRequest
{
    /** Valeur sentinelle : les modèles sans client. */
    public const string GLOBAL_SCOPE = 'global';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'organizationId' => ['sometimes', 'ulid'],
            'customerId' => ['sometimes', 'string', 'max:26'],
            'serviceId' => ['sometimes', 'ulid'],
            'channel' => ['sometimes', 'string', 'max:32'],
            'templateType' => ['sometimes', 'string', 'max:32'],
            'language' => ['sometimes', 'string', 'max:10'],
            'isDefault' => ['sometimes', 'boolean'],
            'isActive' => ['sometimes', 'boolean'],
        ]);
    }
}
