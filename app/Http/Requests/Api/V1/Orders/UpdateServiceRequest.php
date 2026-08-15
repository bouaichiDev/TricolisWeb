<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
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
            'code' => ['sometimes', 'string', 'max:64'],
            'name' => ['sometimes', 'string', 'max:255'],
            'unit' => ['sometimes', 'string', 'max:32'],
            'defaultDurationMinutes' => ['sometimes', 'integer', 'min:0'],
            'billableToCustomer' => ['sometimes', 'boolean'],
            'payableToProvider' => ['sometimes', 'boolean'],
            'requiresAddress' => ['sometimes', 'boolean'],
            'requiresContact' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'max:32'],
        ];
    }
}
