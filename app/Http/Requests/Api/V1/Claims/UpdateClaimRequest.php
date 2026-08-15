<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Claims;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Modification d'une réclamation, clôture comprise.
 *
 * `customerId` n'est pas modifiable : transférer une réclamation d'un client à
 * l'autre ferait perdre la trace du dossier d'origine. `createdAt` et
 * `createdBy` non plus.
 *
 * La cohérence de `closedAt` avec `createdAt` est vérifiée par
 * `ClaimScopeGuard` : elle a besoin de la date d'ouverture enregistrée, que la
 * Request ne connaît pas.
 */
class UpdateClaimRequest extends FormRequest
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
            'orderId' => ['sometimes', 'nullable', 'ulid'],
            'orderServiceId' => ['sometimes', 'nullable', 'ulid'],
            'tourId' => ['sometimes', 'nullable', 'ulid'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'claimType' => ['sometimes', 'string', 'max:64'],
            'cause' => ['sometimes', 'nullable', 'string', 'max:255'],
            'decision' => ['sometimes', 'nullable', 'string'],
            'followUp' => ['sometimes', 'nullable', 'string'],
            'result' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', 'max:32'],
            'responsibleUserId' => ['sometimes', 'nullable', 'ulid'],
            'closedAt' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
