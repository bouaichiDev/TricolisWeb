<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Organizations;

use App\Modules\Organizations\Enums\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
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
            'planCode' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(SubscriptionStatus::class)],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date', 'after:startsAt'],
            'trialEndsAt' => ['nullable', 'date'],
        ];
    }
}
