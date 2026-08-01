<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Drivers;

use App\Shared\Http\Requests\ListRequest;

class ListDriverRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'providerId' => ['sometimes', 'ulid'],
            'userId' => ['sometimes', 'ulid'],
            'legacyId' => ['sometimes', 'integer', 'min:0'],
        ]);
    }
}
