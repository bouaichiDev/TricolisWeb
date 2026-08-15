<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Organizations;

use App\Shared\Menu\MenuCatalogue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Réglage du menu d'une organisation.
 *
 * Seuls la visibilité et la position sont acceptés : la route, l'icône et le
 * libellé appartiennent au catalogue, en code. Les accepter ici permettrait de
 * saisir une route qui n'existe pas dans le routeur React — et l'écran
 * afficherait « Page introuvable ».
 *
 * Le code est confronté au catalogue : un code inconnu est refusé plutôt
 * qu'enregistré, une ligne orpheline ne servant à rien.
 */
class UpdateMenuRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.code' => ['required', 'string', Rule::in(MenuCatalogue::codes())],
            'items.*.isVisible' => ['sometimes', 'boolean'],
            'items.*.position' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
