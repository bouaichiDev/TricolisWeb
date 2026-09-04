<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Contacts;

use App\Shared\Database\MorphMap;
use App\Shared\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

/**
 * Filtres propres aux contacts, en plus des filtres de liste communs.
 *
 * `entityType` / `entityId` bornent la liste aux contacts d'une entité — ceux
 * d'un client, ceux d'un site. La création acceptait déjà ces clés pour créer
 * la liaison ; la lecture ne les proposait pas, ce qui rendait impossible de
 * savoir quels contacts appartiennent à quel client.
 */
class ListContactRequest extends ListRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'isActive' => ['sometimes', 'boolean'],
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
