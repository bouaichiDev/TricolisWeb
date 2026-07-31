<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Contacts;

use App\Shared\Http\Requests\ListRequest;

/**
 * Filtres propres aux contacts, en plus des filtres de liste communs.
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
        ]);
    }
}
