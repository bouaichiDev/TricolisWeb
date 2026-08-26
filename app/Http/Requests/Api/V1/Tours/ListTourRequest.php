<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tours;

use App\Shared\Http\Requests\ListRequest;

/**
 * Filtres propres aux tournées.
 *
 * `organizationId` n'est pas accepté : la liste est toujours restreinte à
 * l'organisation active, portée par l'en-tête.
 */
class ListTourRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'agencyId' => ['sometimes', 'ulid'],
            'depotId' => ['sometimes', 'ulid'],
            'providerId' => ['sometimes', 'ulid'],
            'driverId' => ['sometimes', 'ulid'],
            'vehicleId' => ['sometimes', 'ulid'],
            'tourDate' => ['sometimes', 'date'],
            'tourDateFrom' => ['sometimes', 'date'],
            'tourDateTo' => ['sometimes', 'date', 'after_or_equal:tourDateFrom'],
            'tourType' => ['sometimes', 'string', 'max:64'],
            // La vue en colonnes montre les arrets sous chaque tournee. Les
            // charger toujours couterait une jointure a qui ne veut qu'une
            // liste.
            'withStops' => ['sometimes', 'boolean'],
        ]);
    }
}
