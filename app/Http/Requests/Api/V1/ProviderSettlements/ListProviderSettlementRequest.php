<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\ProviderSettlements;

use App\Shared\Http\Requests\ListRequest;

class ListProviderSettlementRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'providerId' => ['sometimes', 'ulid'],
            'settlementNumber' => ['sometimes', 'string', 'max:255'],
            'periodFrom' => ['sometimes', 'date'],
            'periodTo' => ['sometimes', 'date'],
        ]);
    }
}
