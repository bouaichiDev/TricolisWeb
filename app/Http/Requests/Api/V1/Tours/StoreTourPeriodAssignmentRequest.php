<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tours;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'une affectation.
 *
 * Trois clés étrangères, pas une de plus : le §17 interdit `sequence`,
 * `status`, `quantity` et `duration` sur cette classe.
 *
 * L'appartenance du service à la tournée et du colis à la commande est vérifiée
 * par `AssignmentConsistency` : ces contrôles traversent plusieurs relations que
 * la Request n'a pas à charger.
 */
class StoreTourPeriodAssignmentRequest extends FormRequest
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
            'tourStopServiceId' => ['required', 'ulid'],
            'packageId' => ['nullable', 'ulid'],
        ];
    }
}
