<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tours;

use App\Shared\Http\Requests\ListRequest;

/**
 * Filtres propres aux périodes (§16).
 */
class ListTourPeriodRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'tourStopId' => ['sometimes', 'ulid'],
            'periodType' => ['sometimes', 'string', 'max:64'],
            'plannedFrom' => ['sometimes', 'date'],
            'plannedTo' => ['sometimes', 'date', 'after_or_equal:plannedFrom'],
            'actualFrom' => ['sometimes', 'date'],
            'actualTo' => ['sometimes', 'date', 'after_or_equal:actualFrom'],
        ]);
    }
}
