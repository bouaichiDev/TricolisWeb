<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tours;

use App\Shared\Http\Rules\ExistsInStatusReferential;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTourPeriodRequest extends FormRequest
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
        $tourId = $this->route('tour')?->id;
        $periodId = $this->route('tourPeriod')?->id;

        return [
            'tourStopId' => ['sometimes', 'nullable', 'ulid'],
            'periodType' => ['sometimes', 'string', 'max:64'],
            'sequence' => [
                'sometimes', 'integer', 'min:1',
                Rule::unique('tour_periods', 'sequence')->where('tour_id', $tourId)->ignore($periodId),
            ],
            'plannedStartAt' => ['sometimes', 'nullable', 'date'],
            'plannedEndAt' => ['sometimes', 'nullable', 'date', 'after_or_equal:plannedStartAt'],
            'actualStartAt' => ['sometimes', 'nullable', 'date'],
            'actualEndAt' => ['sometimes', 'nullable', 'date', 'after_or_equal:actualStartAt'],
            'breakMinutes' => ['sometimes', 'integer', 'min:0'],
            'serviceMinutes' => ['sometimes', 'integer', 'min:0'],
            'waitingMinutes' => ['sometimes', 'integer', 'min:0'],
            'distanceMeters' => ['sometimes', 'integer', 'min:0'],
            'internalRemark' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', 'max:32', new ExistsInStatusReferential('tour_period')],
        ];
    }
}
