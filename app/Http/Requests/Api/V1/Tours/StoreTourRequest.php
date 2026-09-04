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
 * Création d'une tournée.
 *
 * `organizationId` n'est pas accepté : la tournée est créée dans l'organisation
 * active. `tourNumber` est fourni par l'appelant — aucune règle de génération
 * n'est définie pour les tournées, et le §9 interdit d'en inventer une.
 *
 * Les contrôles croisés (dépôt rattaché à l'agence, chauffeur et véhicule
 * rattachés au fournisseur) relèvent de `TourReferenceResolver` : ils ont
 * besoin des valeurs déjà enregistrées, que la Request ne connaît pas.
 */
class StoreTourRequest extends FormRequest
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
            // `tourNumber` n'est pas accepte : le serveur l'attribue. Le
            // refuser plutot que l'ignorer evite qu'un appelant croie l'avoir
            // choisi. Decision du 27 aout 2026.
            'tourNumber' => ['prohibited'],
            'tourDate' => ['required', 'date'],
            'agencyId' => [
                'required', 'ulid',
                new BelongsToActiveOrganization(Agency::class, null, 'Cette agence n’appartient pas à l’organisation active.'),
            ],
            'depotId' => ['nullable', 'ulid', Rule::exists('depots', 'id')],
            'providerId' => [
                'nullable', 'ulid',
                new BelongsToActiveOrganization(Provider::class, null, 'Ce fournisseur n’appartient pas à l’organisation active.'),
            ],
            'vehicleId' => ['nullable', 'ulid', Rule::exists('vehicles', 'id')],
            'driverId' => ['nullable', 'ulid', Rule::exists('drivers', 'id')],
            'tourType' => ['nullable', 'string', 'max:64'],
            'instructions' => ['nullable', 'string'],
            'plannedStartAt' => ['nullable', 'date'],
            'plannedEndAt' => ['nullable', 'date', 'after_or_equal:plannedStartAt'],
            'actualStartAt' => ['nullable', 'date'],
            'actualEndAt' => ['nullable', 'date', 'after_or_equal:actualStartAt'],
            'drivingTimeMinutes' => ['sometimes', 'integer', 'min:0'],
            'workingTimeMinutes' => ['sometimes', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(TourStatus::class)],
        ];
    }
}
