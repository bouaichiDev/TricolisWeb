<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Exports;

use App\Shared\Database\MorphMap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Demande d'export.
 *
 * `customerId` n'est pas accepté : il est déduit de la configuration. Les
 * champs de traitement — `fileName`, `storagePath`, `generatedAt`, `sentAt`,
 * `errorMessage`, `attemptCount` — non plus : ils appartiennent au traitement.
 *
 * `entityType` n'accepte que des alias **dérivés** de la morph map, comme
 * `StockMovement.sourceEntityType` en Phase 7.
 */
class StoreExportJobRequest extends FormRequest
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
            'configurationId' => ['required', 'ulid'],
            'entityType' => ['nullable', 'string', Rule::in(array_keys(MorphMap::registered()))],
            'entityId' => ['nullable', 'ulid', 'required_with:entityType'],
            'status' => ['required', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['entityType.in' => 'Ce type d’entité n’est pas reconnu.'];
    }
}
