<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Claims;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'une réclamation : seulement ce que le client décrit (§5).
 *
 * La commande est facultative, comme dans le diagramme
 * (`Order "0..1" -- "0..*" Claim`) ; un service, lui, se désigne toujours
 * avec sa commande. Leur appartenance au client est vérifiée par
 * `ClaimScopeGuard`, pas ici.
 */
class StoreCustomerClaimRequest extends FormRequest
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
            ...CustomerClaimRules::prohibited(),
            'orderId' => ['nullable', 'string', 'ulid', 'required_with:orderServiceId'],
            'orderServiceId' => ['nullable', 'string', 'ulid'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'claimType' => ['required', 'string', 'max:64'],
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
