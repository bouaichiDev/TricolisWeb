<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Claims;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Modification d'une réclamation ouverte : titre, description, type (§11).
 *
 * Commande et service ne se changent pas : une réclamation déplacée d'une
 * commande à l'autre ne serait plus celle que le transporteur instruit.
 */
class UpdateCustomerClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...CustomerClaimRules::prohibited(['orderId', 'orderServiceId']),
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'claimType' => ['sometimes', 'required', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return CustomerClaimRules::messages();
    }
}
