<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Contacts;

use App\Shared\Enums\ContactRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntityContactRequest extends FormRequest
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
            'entityType' => ['required', 'string', 'max:64'],
            'entityId' => ['required', 'ulid'],
            'contactRole' => ['sometimes', Rule::enum(ContactRole::class)],
            'isPrimary' => ['sometimes', 'boolean'],
            'notifyByEmail' => ['sometimes', 'boolean'],
            'notifyBySms' => ['sometimes', 'boolean'],
        ];
    }
}
