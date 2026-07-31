<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Organizations;

use App\Modules\Organizations\Enums\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionRequest extends FormRequest
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
            'planCode' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(SubscriptionStatus::class)],
            'startsAt' => ['sometimes', 'nullable', 'date'],
            'endsAt' => ['sometimes', 'nullable', 'date', 'after:startsAt'],
            'trialEndsAt' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
