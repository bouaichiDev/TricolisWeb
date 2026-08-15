<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:32'],
            'defaultDurationMinutes' => ['required', 'integer', 'min:0'],
            'billableToCustomer' => ['required', 'boolean'],
            'payableToProvider' => ['required', 'boolean'],
            'requiresAddress' => ['required', 'boolean'],
            'requiresContact' => ['required', 'boolean'],
            'status' => ['sometimes', 'string', 'max:32'],
        ];
    }
}
