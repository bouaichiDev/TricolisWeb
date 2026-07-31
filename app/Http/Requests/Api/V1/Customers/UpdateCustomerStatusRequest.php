<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customers;

use App\Modules\Customers\Enums\CustomerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(CustomerStatus::class)]];
    }
}
