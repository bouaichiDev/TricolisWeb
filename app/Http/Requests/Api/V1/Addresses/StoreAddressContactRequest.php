<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Addresses;

use App\Shared\Enums\ContactRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAddressContactRequest extends FormRequest
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
            'contactId' => ['required', 'ulid'],
            'contactRole' => ['sometimes', Rule::enum(ContactRole::class)],
            'isPrimary' => ['sometimes', 'boolean'],
        ];
    }
}
