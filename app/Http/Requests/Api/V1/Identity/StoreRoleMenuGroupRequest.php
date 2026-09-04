<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Shared\Menu\MenuIcons;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'un groupe de menu sur un rôle.
 *
 * Un nom et une icône, rien de plus. **Pas de route, pas de permission** — et
 * c'est précisément ce qui autorise ce groupe à naître en base alors que le
 * catalogue vit en code : il n'ouvre rien, il range. Les entrées qu'on y mettra
 * gardent, elles, la destination que le code leur donne.
 *
 * Le `code` n'est pas demandé : le serveur le tire, opaque et immuable. Le
 * laisser saisir permettrait de heurter un code du catalogue, et l'on réglerait
 * une entrée en croyant en régler une autre.
 */
class StoreRoleMenuGroupRequest extends FormRequest
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
            'label' => ['required', 'string', 'max:60'],
            'icon' => ['required', 'string', Rule::in(MenuIcons::NAMES)],
        ];
    }
}
