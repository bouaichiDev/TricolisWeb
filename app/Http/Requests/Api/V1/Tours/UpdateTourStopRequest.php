<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tours;

use App\Modules\Tours\Enums\TourStopStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTourStopRequest extends FormRequest
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
        $stopId = $this->route('tourStop')?->id;

        return [
            'addressId' => ['sometimes', 'string', Rule::exists('addresses', 'id')],
            'sequence' => [
                'sometimes', 'integer', 'min:1',
                Rule::unique('tour_stops', 'sequence')->where('tour_id', $tourId)->ignore($stopId),
            ],
            'groupingKey' => ['sometimes', 'nullable', 'string', 'max:255'],
            'generationMode' => ['sometimes', 'nullable', 'string', 'max:64'],
            'plannedArrivalAt' => ['sometimes', 'nullable', 'date'],
            'plannedDepartureAt' => ['sometimes', 'nullable', 'date', 'after_or_equal:plannedArrivalAt'],
            'actualArrivalAt' => ['sometimes', 'nullable', 'date'],
            'actualDepartureAt' => ['sometimes', 'nullable', 'date', 'after_or_equal:actualArrivalAt'],
            'waitingMinutes' => ['sometimes', 'integer', 'min:0'],
            'serviceMinutes' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::enum(TourStopStatus::class)],
        ];
    }
}
