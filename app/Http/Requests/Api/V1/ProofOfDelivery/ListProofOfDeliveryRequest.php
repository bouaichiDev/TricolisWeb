<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\ProofOfDelivery;

use App\Shared\Http\Requests\ListRequest;

class ListProofOfDeliveryRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'orderId' => ['sometimes', 'ulid'],
            'orderServiceId' => ['sometimes', 'ulid'],
            'tourStopId' => ['sometimes', 'ulid'],
            'createdBy' => ['sometimes', 'ulid'],
            'deliveredFrom' => ['sometimes', 'date'],
            'deliveredTo' => ['sometimes', 'date', 'after_or_equal:deliveredFrom'],
        ]);
    }
}
