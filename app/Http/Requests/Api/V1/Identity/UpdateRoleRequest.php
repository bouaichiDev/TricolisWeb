<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Modification d'un rôle.
 *
 * `code`, `scope` et `isSystem` sont absents des règles, donc sans effet :
 *
 * - `code` identifie le rôle pour les vérifications qui s'y adossent ;
 * - `scope` transformerait un rôle local en rôle plateforme — l'élévation même
 *   que cette phase corrige ;
 * - `isSystem` rendrait un rôle intouchable, ou rendrait modifiable un rôle
 *   livré avec l'application.
 *
 * `scope` était accepté avant cette correction, et le contrôleur l'écrivait tel
 * quel.
 */
class UpdateRoleRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:20'],
            'permissionIds' => ['sometimes', 'array'],
            'permissionIds.*' => ['required', 'ulid', 'distinct', 'exists:permissions,id'],
        ];
    }
}
