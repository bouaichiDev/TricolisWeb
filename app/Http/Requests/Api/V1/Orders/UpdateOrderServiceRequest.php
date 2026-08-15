<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'serviceId' => ['sometimes', 'ulid'],
            'addressId' => ['sometimes', 'ulid'],
            'serviceNumber' => ['sometimes', 'string', 'max:255'],
            'sequence' => ['sometimes', 'integer', 'min:1'],
            'requestedDate' => ['sometimes', 'date'],
            'requestedFrom' => ['sometimes', 'nullable', 'date'],
            'requestedTo' => ['sometimes', 'nullable', 'date', 'after_or_equal:requestedFrom'],
            'quantity' => ['sometimes', 'numeric', 'gt:0'],
            'unit' => ['sometimes', 'string', 'max:32'],
            'requiredTimeMinutes' => ['sometimes', 'integer', 'min:0'],
            'remainingTimeMinutes' => ['sometimes', 'integer', 'min:0'],
            'weight' => ['sometimes', 'numeric', 'min:0'],
            'volume' => ['sometimes', 'numeric', 'min:0'],
            'packageCount' => ['sometimes', 'integer', 'min:0'],
            'customerUnitPrice' => ['sometimes', 'numeric', 'min:0'],
            'customerTotalPrice' => ['sometimes', 'numeric', 'min:0'],
            'providerUnitCost' => ['sometimes', 'numeric', 'min:0'],
            'providerTotalCost' => ['sometimes', 'numeric', 'min:0'],
            'instructions' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
