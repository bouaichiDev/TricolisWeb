<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Addresses;

use App\Shared\Database\MorphMap;
use App\Shared\Http\Requests\ListRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Filtres de la liste des adresses.
 *
 * `entityType` / `entityId` répondent à une question que l'API ne savait pas
 * poser : **quelles adresses appartiennent à ce client ?** La création les
 * acceptait déjà — `StoreAddressRequest` s'en sert pour créer la liaison — mais
 * la lecture ne les proposait pas. Il fallait donc lister toutes les adresses
 * de l'organisation puis interroger les liaisons une par une.
 *
 * Les alias autorisés sont ceux de `StoreAddressRequest` : les deux extrémités
 * de la même relation parlent le même vocabulaire.
 */
class ListAddressRequest extends ListRequest
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
        return array_merge(parent::rules(), [
            'entityType' => ['sometimes', 'string', Rule::in($this->allowedEntityTypes())],
            'entityId' => ['required_with:entityType', 'ulid'],
        ]);
    }

    public function hasEntityFilter(): bool
    {
        return $this->filled('entityType') && $this->filled('entityId');
    }

    /**
     * @return array<int, string>
     */
    private function allowedEntityTypes(): array
    {
        return [
            MorphMap::ORGANIZATION,
            MorphMap::CUSTOMER,
            MorphMap::CUSTOMER_SITE,
            MorphMap::AGENCY,
            MorphMap::DEPOT,
        ];
    }
}
