<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Organizations;

use App\Shared\Enums\OrganizationStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
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
        /** @var string|null $organizationId */
        $organizationId = $this->route('organization')?->id ?? $this->route('organization');

        return [
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('organizations', 'code')->ignore($organizationId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'legalName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'registrationNumber' => ['sometimes', 'nullable', 'string', 'max:255'],
            'taxNumber' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'preferredLanguage' => ['sometimes', 'string', 'max:10'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'currencyCode' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', 'string', Rule::enum(OrganizationStatus::class)],
            'settings' => ['sometimes', 'array'],
        ];
    }
}
