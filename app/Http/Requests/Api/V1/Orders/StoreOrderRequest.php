<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customerId' => ['required', 'ulid'], 'agencyId' => ['required', 'ulid'], 'depotId' => ['nullable', 'ulid'], 'orderNumber' => ['required', 'string', 'max:255'], 'externalReference' => ['nullable', 'string', 'max:255'], 'customerReference' => ['nullable', 'string', 'max:255'], 'orderType' => ['nullable', 'string', 'max:64'], 'orderDate' => ['required', 'date'], 'source' => ['sometimes', Rule::enum(OrderSource::class)], 'currencyCode' => ['sometimes', 'string', 'size:3', 'uppercase'], 'status' => ['sometimes', Rule::enum(OrderStatus::class)], 'internalRemark' => ['nullable', 'string'], 'workerRemark' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'], 'lines.*.name' => ['required', 'string', 'max:255'], 'lines.*.articleCode' => ['nullable', 'string', 'max:255'], 'lines.*.barcode' => ['nullable', 'string', 'max:255'], 'lines.*.description' => ['nullable', 'string'], 'lines.*.quantity' => ['required', 'numeric', 'gt:0'], 'lines.*.weight' => ['sometimes', 'numeric', 'min:0'], 'lines.*.volume' => ['sometimes', 'numeric', 'min:0'], 'lines.*.sellingPrice' => ['nullable', 'numeric', 'min:0'],
            'services' => ['required', 'array', 'min:1'], 'services.*.serviceId' => ['required', 'ulid'], 'services.*.addressId' => ['required', 'ulid'], 'services.*.serviceNumber' => ['required', 'string', 'max:255'], 'services.*.sequence' => ['required', 'integer', 'min:1'], 'services.*.requestedDate' => ['required', 'date'], 'services.*.requestedFrom' => ['nullable', 'date'], 'services.*.requestedTo' => ['nullable', 'date'], 'services.*.quantity' => ['required', 'numeric', 'gt:0'], 'services.*.unit' => ['required', 'string', 'max:32'], 'services.*.requiredTimeMinutes' => ['required', 'integer', 'min:0'], 'services.*.remainingTimeMinutes' => ['required', 'integer', 'min:0'], 'services.*.weight' => ['required', 'numeric', 'min:0'], 'services.*.volume' => ['required', 'numeric', 'min:0'], 'services.*.packageCount' => ['required', 'integer', 'min:0'], 'services.*.customerUnitPrice' => ['required', 'numeric', 'min:0'], 'services.*.customerTotalPrice' => ['required', 'numeric', 'min:0'], 'services.*.providerUnitCost' => ['required', 'numeric', 'min:0'], 'services.*.providerTotalCost' => ['required', 'numeric', 'min:0'], 'services.*.instructions' => ['nullable', 'string'], 'services.*.status' => ['required', Rule::enum(OrderServiceStatus::class)],
        ];
    }
}
