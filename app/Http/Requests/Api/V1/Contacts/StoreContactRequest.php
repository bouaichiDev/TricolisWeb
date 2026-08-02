<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Contacts;

use App\Shared\Database\MorphMap;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
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
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'preferredLanguage' => ['sometimes', 'string', 'max:10'],
            'isActive' => ['sometimes', 'boolean'],
            'entityType' => ['nullable', 'string', Rule::in($this->allowedEntityTypes())],
            'entityId' => ['required_with:entityType', 'ulid'],
            'contactRole' => ['nullable', 'string', 'max:32'],
            'isPrimary' => ['sometimes', 'boolean'],
            'notifyByEmail' => ['sometimes', 'boolean'],
            'notifyBySms' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedEntityTypes(): array
    {
        return [
            MorphMap::ORGANIZATION,
            MorphMap::CUSTOMER,
            MorphMap::CUSTOMER_SITE,
            MorphMap::AGENCY,
            MorphMap::DEPOT,
        ];
    }
}
