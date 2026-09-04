<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tours;

use App\Shared\Http\Rules\ExistsInStatusReferential;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une période.
 *
 * `tourStopId` est facultatif — une période de conduite entre deux arrêts
 * n'appartient à aucun arrêt. Son appartenance à la tournée est vérifiée par
 * `CreateTourPeriodAction`.
 *
 * `periodType` et `status` restent des chaînes libres : le diagramme n'en
 * énumère aucune valeur.
 */
class StoreTourPeriodRequest extends FormRequest
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
            'tourStopId' => ['nullable', 'ulid'],
            'periodType' => ['required', 'string', 'max:64'],
            'sequence' => [
                'required', 'integer', 'min:1',
                Rule::unique('tour_periods', 'sequence')->where('tour_id', $tourId),
            ],
            'plannedStartAt' => ['nullable', 'date'],
            'plannedEndAt' => ['nullable', 'date', 'after_or_equal:plannedStartAt'],
            'actualStartAt' => ['nullable', 'date'],
            'actualEndAt' => ['nullable', 'date', 'after_or_equal:actualStartAt'],
            'breakMinutes' => ['sometimes', 'integer', 'min:0'],
            'serviceMinutes' => ['sometimes', 'integer', 'min:0'],
            'waitingMinutes' => ['sometimes', 'integer', 'min:0'],
            'distanceMeters' => ['sometimes', 'integer', 'min:0'],
            'internalRemark' => ['nullable', 'string'],
            'status' => ['required', 'string', 'max:32', new ExistsInStatusReferential('tour_period')],
        ];
    }
}
