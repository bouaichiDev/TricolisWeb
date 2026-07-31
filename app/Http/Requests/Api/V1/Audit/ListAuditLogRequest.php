<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Audit;

use App\Shared\Http\Requests\ListRequest;

/**
 * Filtres propres au journal d'audit, en plus des filtres de liste communs.
 */
class ListAuditLogRequest extends ListRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'userId' => ['sometimes', 'ulid'],
            'action' => ['sometimes', 'string', 'max:64'],
            'entityType' => ['sometimes', 'string', 'max:64'],
            'entityId' => ['sometimes', 'ulid'],
        ]);
    }
}
