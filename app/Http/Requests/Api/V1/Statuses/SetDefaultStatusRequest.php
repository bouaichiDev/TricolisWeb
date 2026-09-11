<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Statuses;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Choix du statut par défaut d'une entité.
 *
 * `statusId` est présent mais peut valoir `null` : c'est la façon de retirer le
 * choix, et la colonne de l'entité reprend alors sa propre valeur par défaut.
 * L'appartenance du statut à l'entité est vérifiée par `SetDefaultStatus`.
 */
class SetDefaultStatusRequest extends FormRequest
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
            'statusId' => ['present', 'nullable', 'ulid'],
        ];
    }
}
