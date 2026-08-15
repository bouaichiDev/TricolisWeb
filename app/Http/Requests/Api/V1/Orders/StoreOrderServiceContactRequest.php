<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use App\Shared\Enums\ContactRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderServiceContactRequest extends FormRequest
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
            'contactId' => ['nullable', 'ulid'],
            'contactRole' => ['sometimes', Rule::enum(ContactRole::class)],
            'isPrimary' => ['sometimes', 'boolean'],
            // Sans contact partagé, l'identité doit être fournie : un contact
            // sans nom ne serait exploitable par personne sur le terrain.
            'firstName' => ['required_without:contactId', 'nullable', 'string', 'max:255'],
            'lastName' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
