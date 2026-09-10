<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Templates;

use App\Modules\Templates\Enums\TemplateCategory;
use App\Shared\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

/**
 * Filtres de la liste des modèles.
 *
 * `customerId` accepte la chaîne `global` en plus d'un identifiant : c'est ce
 * qui permet à l'écran de n'afficher que les modèles du transporteur, sans quoi
 * « aucun client » et « tous les clients » se demanderaient de la même façon.
 *
 * `category` et `templateType` se cumulent, du plus large au plus précis : la
 * catégorie ouvre le rayon, le type y désigne une étagère.
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
            // La categorie regroupe les natures : « communication » se
            // definit par la negative, et une liste de onze valeurs dans
            // l'URL aurait oublie la douzieme au premier ajout.
            'category' => ['sometimes', Rule::in(TemplateCategory::values())],
            'language' => ['sometimes', 'string', 'max:10'],
            'isDefault' => ['sometimes', 'boolean'],
            'isActive' => ['sometimes', 'boolean'],
        ]);
    }
}
