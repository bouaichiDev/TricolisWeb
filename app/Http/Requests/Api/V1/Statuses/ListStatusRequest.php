<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Statuses;

use App\Shared\Http\Requests\ListRequest;

/** Filtres propres au référentiel des statuts. */
class ListStatusRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'source' => ['sometimes', 'string', 'max:64'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }
}
