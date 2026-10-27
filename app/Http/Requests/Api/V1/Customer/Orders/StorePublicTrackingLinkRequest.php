<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Orders;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Génération d'un lien public. `expiresAt`, facultatif, est le dernier jour
 * de validité ; sans lui, la durée réglée par le transporteur s'applique, ou
 * aucune.
 */
class StorePublicTrackingLinkRequest extends FormRequest
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
            'expiresAt' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expiresAt.after_or_equal' => 'La date d’expiration ne peut pas être passée.',
            'expiresAt.date_format' => 'La date d’expiration doit être une date (AAAA-MM-JJ).',
        ];
    }
}
