<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tours;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTourPeriodAssignmentRequest extends FormRequest
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
            'tourStopServiceId' => ['sometimes', 'ulid'],
            'packageId' => ['sometimes', 'nullable', 'ulid'],
        ];
    }
}
