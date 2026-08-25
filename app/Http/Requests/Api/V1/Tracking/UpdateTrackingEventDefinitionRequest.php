<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tracking;

use App\Shared\Database\MorphMap;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Une étape du parcours client.
 *
 * `sourceType` est validé contre la **morph map** : recopier ici la liste des
 * entités la ferait diverger à la première ajoutée, et accepter n'importe quelle
 * chaîne créerait des étapes que rien ne déclencherait jamais.
 *
 * `statusCode` reste libre : les statuts vivent en base, décrits par le
 * référentiel, et un organisme peut en ajouter.
 */
class UpdateTrackingEventDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sourceType' => ['sometimes', 'string', Rule::in(array_keys(MorphMap::registered()))],
            'statusCode' => ['sometimes', 'string', 'max:64'],
            'code' => ['sometimes', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:64'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            // Non nulle, l'etape est suivie en direct — et on sait par quoi.
            'apiConfigurationId' => ['sometimes', 'nullable', 'ulid'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sourceType.in' => 'Cette entité ne peut pas porter d’étape de parcours.',
        ];
    }
}
