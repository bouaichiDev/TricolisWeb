<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tours;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification d'un service planifié.
 *
 * `orderServiceId` n'est pas modifiable : changer le service revient à une
 * autre affectation, qui doit laisser une trace. On désactive puis on recrée.
 */
class UpdateTourStopServiceRequest extends FormRequest
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
        $stopId = $this->route('tourStop')?->id;
        $serviceId = $this->route('tourStopService')?->id;

        return [
            'sequenceWithinStop' => [
                'sometimes', 'integer', 'min:1',
                Rule::unique('tour_stop_services', 'sequence_within_stop')->where('tour_stop_id', $stopId)->ignore($serviceId),
            ],
            'isActiveAssignment' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'max:32'],
        ];
    }
}
