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
class StoreTrackingEventDefinitionRequest extends FormRequest
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
            'sourceType' => ['required', 'string', Rule::in(array_keys(MorphMap::registered()))],
            'statusCode' => ['required', 'string', 'max:64'],
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:64'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            // Non nulle, l'etape est suivie en direct — et on sait par quoi.
            'apiConfigurationId' => ['sometimes', 'nullable', 'ulid'],
            // De quelle prestation l'etape parle. Nulle, elle vaut pour toutes :
            // une commande porte souvent chargement, livraison et montage, et
            // le destinataire ne suit que la sienne.
            'serviceId' => ['sometimes', 'nullable', 'ulid'],
            // Ce que le client final voit. Le chargement au depot interesse le
            // planificateur, jamais le destinataire.
            'visibleToCustomer' => ['sometimes', 'boolean'],
            // La preuve s'attache a l'etape qui la produit : offerte des
            // « planifie », elle n'existe pas encore.
            'showsProofOfDelivery' => ['sometimes', 'boolean'],
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
