<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Agencies;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAgencyRequest extends FormRequest
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
            'shortName' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:7'],
            'loadingPoint' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:20'],
        ];
    }
}
