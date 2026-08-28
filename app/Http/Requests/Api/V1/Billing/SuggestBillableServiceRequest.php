<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ce qu'on accepte de compléter.
 *
 * Le champ vient d'une liste fermée : sans elle, un appelant choisirait la
 * colonne à parcourir, et le filtre deviendrait une lecture libre de la base.
 */
class SuggestBillableServiceRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'field' => ['required', Rule::in(['service', 'order'])],
            'term' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
