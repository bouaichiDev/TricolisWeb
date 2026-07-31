<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customers;

use App\Modules\Customers\Enums\CustomerStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
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
            'code' => ['sometimes', 'string', 'max:64'],
            'name' => ['sometimes', 'string', 'max:255'],
            'legalName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'paymentMode' => ['sometimes', 'nullable', 'string', 'max:64'],
            'communicationMode' => ['sometimes', 'nullable', 'string', 'max:64'],
            'catalogEnabled' => ['sometimes', 'boolean'],
            'stockEnabled' => ['sometimes', 'boolean'],
            'packageEnabled' => ['sometimes', 'boolean'],
            'appointmentEnabled' => ['sometimes', 'boolean'],
            'trackingEnabled' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::enum(CustomerStatus::class)],
        ];
    }
}
