<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tours;

use App\Modules\Addresses\Models\Address;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Shared\Http\Rules\BelongsToActiveOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un arrêt, services compris.
 *
 * `services` est **obligatoire et non vide** : la cardinalité
 * `TourStop "1" *-- "1..*" TourStopService` interdit un arrêt sans service.
 * C'est ici que la règle se voit, avant même d'atteindre l'Action.
 *
 * `addressId` est cloisonné par organisation. `addresses` n'a pas
 * d'`organization_id` — elle le tient de ses liaisons — et une simple
 * vérification d'existence laissait rattacher à un arrêt l'adresse d'une autre
 * organisation, que la tournée rendait ensuite lisible.
 */
class StoreTourStopRequest extends FormRequest
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

        return [
            'addressId' => [
                'required', 'ulid',
                new BelongsToActiveOrganization(Address::class, 'entityAddresses', 'Cette adresse n’appartient pas à l’organisation active.'),
            ],
            'sequence' => [
                'required', 'integer', 'min:1',
                Rule::unique('tour_stops', 'sequence')->where('tour_id', $tourId),
            ],
            'groupingKey' => ['nullable', 'string', 'max:255'],
            'generationMode' => ['nullable', 'string', 'max:64'],
            'plannedArrivalAt' => ['nullable', 'date'],
            'plannedDepartureAt' => ['nullable', 'date', 'after_or_equal:plannedArrivalAt'],
            'actualArrivalAt' => ['nullable', 'date'],
            'actualDepartureAt' => ['nullable', 'date', 'after_or_equal:actualArrivalAt'],
            'waitingMinutes' => ['sometimes', 'integer', 'min:0'],
            'serviceMinutes' => ['sometimes', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(TourStopStatus::class)],

            'services' => ['required', 'array', 'min:1'],
            'services.*.orderServiceId' => ['required', 'ulid'],
            'services.*.sequenceWithinStop' => ['required', 'integer', 'min:1'],
            'services.*.isActiveAssignment' => ['sometimes', 'boolean'],
            'services.*.status' => ['required', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'services.required' => 'Un arrêt doit porter au moins un service.',
            'services.min' => 'Un arrêt doit porter au moins un service.',
        ];
    }
}
