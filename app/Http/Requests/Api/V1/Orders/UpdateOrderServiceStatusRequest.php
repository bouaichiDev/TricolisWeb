<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use App\Modules\Orders\Enums\OrderServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderServiceStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(OrderServiceStatus::class)],
        ];
    }
}
