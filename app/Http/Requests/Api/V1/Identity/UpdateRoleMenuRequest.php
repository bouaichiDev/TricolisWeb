<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Identity;

use App\Shared\Menu\MenuCodes;
use App\Shared\Menu\MenuIcons;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Réglage du menu d'un rôle.
 *
 * Cinq choix sont acceptés : la **visibilité**, la **position**, le **libellé**,
 * l'**icône** et le **rattachement**. Aucun ne peut fabriquer un menu cassé —
 * au pire une entrée mal nommée ou mal rangée, que le même écran corrige.
 *
 * Ce qui reste refusé n'a pas changé de nature : ni `route` ni `permission`.
 * Une route saisie ici pourrait ne pas exister dans le routeur React, et
 * l'écran afficherait « Page introuvable » ; une permission inventée ouvrirait
 * une entrée vers un écran interdit.
 *
 * L'icône est confrontée à `MenuIcons` : le frontend résout un **nom** vers un
 * composant, et un nom qu'il ignore retomberait silencieusement sur une icône
 * neutre — l'administrateur croirait avoir choisi. Le code, lui, est confronté
 * au catalogue **et aux groupes que ce rôle s'est créés** : une ligne orpheline
 * ne servirait à rien.
 *
 * `label` accepte `null` — et c'est le geste qui **revient au libellé livré**,
 * traductions comprises. Sans lui, renommer serait irréversible.
 *
 * `parent` n'accepte qu'un **groupe**, livré ou créé, ou `null` pour le premier
 * niveau : la barre latérale rend deux niveaux, pas trois.
 */
class UpdateRoleMenuRequest extends FormRequest
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
        // Le rôle vient de l'URL : les codes acceptés dépendent des groupes
        // qu'il s'est créés, et un groupe d'un autre rôle n'a rien à faire ici.
        $roleId = $this->route('role')?->id;

        return [
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.code' => ['required', 'string', Rule::in(MenuCodes::settable($roleId))],
            'items.*.isVisible' => ['sometimes', 'boolean'],
            'items.*.position' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'items.*.label' => ['sometimes', 'nullable', 'string', 'max:60'],
            'items.*.icon' => ['sometimes', 'nullable', 'string', Rule::in(MenuIcons::NAMES)],
            'items.*.parent' => ['sometimes', 'nullable', 'string', Rule::in(MenuCodes::groups($roleId))],
        ];
    }
}
