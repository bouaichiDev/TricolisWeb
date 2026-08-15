<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Claims;

use App\Shared\Http\Requests\ListRequest;

/**
 * Filtres propres aux réclamations (§17).
 */
class ListClaimRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'customerId' => ['sometimes', 'ulid'],
            'orderId' => ['sometimes', 'ulid'],
            'orderServiceId' => ['sometimes', 'ulid'],
            'tourId' => ['sometimes', 'ulid'],
            'claimType' => ['sometimes', 'string', 'max:64'],
            'responsibleUserId' => ['sometimes', 'ulid'],
            'closedFrom' => ['sometimes', 'date'],
            'closedTo' => ['sometimes', 'date', 'after_or_equal:closedFrom'],
        ]);
    }
}
