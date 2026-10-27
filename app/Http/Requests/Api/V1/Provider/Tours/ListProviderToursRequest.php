<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Provider\Tours;

use App\Modules\ProviderPortal\Services\ProviderTourScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtres de la liste des tournées : période, statut visible, numéro.
 */
class ListProviderToursRequest extends FormRequest
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
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(ProviderTourScope::visibleStatuses())],
            'search' => ['nullable', 'string', 'max:64'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:10', 'max:100'],
            'providerId' => ['missing'],
            'organizationId' => ['missing'],
        ];
    }
}
