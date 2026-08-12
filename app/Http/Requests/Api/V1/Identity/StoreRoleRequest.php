<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'un rôle.
 *
 * `scope`, `isSystem` et `organizationId` ne figurent plus dans les règles :
 * une clé absente des règles est absente de `validated()`, donc jamais écrite en
 * base. Le contrôleur impose `scope = organization`, `is_system = false` et
 * l'organisation active.
 *
 * Les rejeter par validation plutôt que les ignorer serait moins sûr en
 * apparence seulement : un attaquant apprendrait quels champs existent. Ils sont
 * simplement sans effet.
 */
class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:20'],
            'permissionIds' => ['sometimes', 'array'],
            'permissionIds.*' => ['required', 'ulid', 'distinct', 'exists:permissions,id'],
        ];
    }
}
