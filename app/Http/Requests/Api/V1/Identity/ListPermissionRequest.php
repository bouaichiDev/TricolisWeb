<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Shared\Http\Requests\ListRequest;

/**
 * Filtres propres au référentiel des permissions.
 */
class ListPermissionRequest extends ListRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'module' => ['sometimes', 'string', 'max:64'],
        ]);
    }
}
