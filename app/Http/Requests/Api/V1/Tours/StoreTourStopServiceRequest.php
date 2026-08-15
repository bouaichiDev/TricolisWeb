<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tours;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Planification d'un service de commande sur un arrêt existant.
 *
 * L'appartenance du service à l'organisation de la tournée est vérifiée par
 * `TourScopeGuard` : elle passe par la commande, que la Request ne charge pas.
 */
class StoreTourStopServiceRequest extends FormRequest
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

        return [
            'orderServiceId' => ['required', 'ulid'],
            'sequenceWithinStop' => [
                'required', 'integer', 'min:1',
                Rule::unique('tour_stop_services', 'sequence_within_stop')->where('tour_stop_id', $stopId),
            ],
            'isActiveAssignment' => ['sometimes', 'boolean'],
            'status' => ['required', 'string', 'max:32'],
        ];
    }
}
