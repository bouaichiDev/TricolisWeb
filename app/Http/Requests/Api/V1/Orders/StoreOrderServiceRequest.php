<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use App\Modules\Orders\Enums\OrderServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderServiceRequest extends FormRequest
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
            'serviceId' => ['required', 'ulid'],
            'addressId' => ['required', 'ulid'],
            'serviceNumber' => ['required', 'string', 'max:255'],
            'sequence' => ['required', 'integer', 'min:1'],
            'requestedDate' => ['required', 'date'],
            'requestedFrom' => ['nullable', 'date'],
            'requestedTo' => ['nullable', 'date', 'after_or_equal:requestedFrom'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['required', 'string', 'max:32'],
            'requiredTimeMinutes' => ['required', 'integer', 'min:0'],
            'remainingTimeMinutes' => ['sometimes', 'integer', 'min:0'],
            'weight' => ['sometimes', 'numeric', 'min:0'],
            'volume' => ['sometimes', 'numeric', 'min:0'],
            'packageCount' => ['sometimes', 'integer', 'min:0'],
            'customerUnitPrice' => ['sometimes', 'numeric', 'min:0'],
            'customerTotalPrice' => ['sometimes', 'numeric', 'min:0'],
            'providerUnitCost' => ['sometimes', 'numeric', 'min:0'],
            'providerTotalCost' => ['sometimes', 'numeric', 'min:0'],
            'instructions' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(OrderServiceStatus::class)],
        ];
    }
}
