<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Provider\Resources;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtres des listes de chauffeurs et de véhicules : recherche libre, statut,
 * taille de page. Aucun filtre ne porte de fournisseur ni d'organisation.
 */
class ListProviderResourcesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:active,inactive,maintenance'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:10', 'max:100'],
            'providerId' => ['prohibited'],
            'organizationId' => ['prohibited'],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('perPage') ?? 25);
    }
}
