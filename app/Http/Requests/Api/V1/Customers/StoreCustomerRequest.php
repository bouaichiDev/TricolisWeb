<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customers;

use App\Modules\Customers\Enums\CustomerStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'legalName' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'paymentMode' => ['nullable', 'string', 'max:64'],
            'communicationMode' => ['nullable', 'string', 'max:64'],
            'catalogEnabled' => ['sometimes', 'boolean'],
            'stockEnabled' => ['sometimes', 'boolean'],
            'packageEnabled' => ['sometimes', 'boolean'],
            'appointmentEnabled' => ['sometimes', 'boolean'],
            'trackingEnabled' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::enum(CustomerStatus::class)],
        ];
    }
}
