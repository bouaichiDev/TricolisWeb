<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tours;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Providers\Models\Provider;
use App\Modules\Tours\Enums\TourStatus;
use App\Shared\Http\Rules\BelongsToActiveOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification d'une tournée.
 *
 * L'organisation n'est pas modifiable : déplacer une tournée emporterait ses
 * arrêts et ses services hors de leur périmètre.
 */
class UpdateTourRequest extends FormRequest
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
            // Le numero est attribue a la creation et ne se renegocie pas :
            // le changer romprait les references deja imprimees ou envoyees.
            'tourNumber' => ['prohibited'],
            'tourDate' => ['sometimes', 'date'],
            'agencyId' => [
                'sometimes', 'ulid',
                new BelongsToActiveOrganization(Agency::class, null, 'Cette agence n’appartient pas à l’organisation active.'),
            ],
            'depotId' => ['sometimes', 'nullable', 'ulid', Rule::exists('depots', 'id')],
            'providerId' => [
                'sometimes', 'nullable', 'ulid',
                new BelongsToActiveOrganization(Provider::class, null, 'Ce fournisseur n’appartient pas à l’organisation active.'),
            ],
            'vehicleId' => ['sometimes', 'nullable', 'ulid', Rule::exists('vehicles', 'id')],
            'driverId' => ['sometimes', 'nullable', 'ulid', Rule::exists('drivers', 'id')],
            'tourType' => ['sometimes', 'nullable', 'string', 'max:64'],
            'instructions' => ['sometimes', 'nullable', 'string'],
            'plannedStartAt' => ['sometimes', 'nullable', 'date'],
            'plannedEndAt' => ['sometimes', 'nullable', 'date', 'after_or_equal:plannedStartAt'],
            'actualStartAt' => ['sometimes', 'nullable', 'date'],
            'actualEndAt' => ['sometimes', 'nullable', 'date', 'after_or_equal:actualStartAt'],
            'drivingTimeMinutes' => ['sometimes', 'integer', 'min:0'],
            'workingTimeMinutes' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::enum(TourStatus::class)],
        ];
    }
}
