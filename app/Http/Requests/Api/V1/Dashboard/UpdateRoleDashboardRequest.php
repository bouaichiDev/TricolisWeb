<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Dashboard;

use App\Shared\Dashboard\DashboardWidgetRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Ce qu'on accepte d'écrire dans la configuration d'un rôle.
 *
 * **Deux champs, et rien d'autre : une clé, un rang.** Pas de requête, pas de
 * nom de classe, pas d'URL, pas de titre libre. Ce n'est pas de la prudence
 * excessive : la colonne est un JSON, et tout ce qu'on y accepterait
 * reviendrait un jour dans une réponse d'API ou dans un rendu. Une clé du
 * catalogue ne peut désigner qu'un résolveur écrit et déployé ; une chaîne
 * libre pourrait désigner n'importe quoi.
 *
 * Un tableau **vide est accepté**, et c'est délibéré : c'est ainsi qu'un rôle
 * dit « aucun widget ». La différence avec l'absence de configuration est le
 * cœur du modèle — voir `RoleDashboardWidgets`.
 *
 * Deux refus supplémentaires, tous deux au nom de la même règle : la
 * configuration n'accorde rien.
 *
 * - **une clé en double** — deux rangs pour un même widget, dont un seul
 *   survivrait, choisi par l'ordre du tableau ;
 * - **un widget dont le rôle n'a pas la permission** — l'accepter aurait laissé
 *   croire qu'il s'affichera. Il ne s'affichera pas : l'intersection avec les
 *   permissions effectives a lieu à chaque chargement. Le refuser ici le dit
 *   tout de suite, et à l'endroit où l'on peut y remédier.
 */
class UpdateRoleDashboardRequest extends FormRequest
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
            // `present` et non `required` : `required` refuse un tableau vide,
            // qui est précisément la façon de dire « ce rôle ne voit rien ».
            'widgets' => ['present', 'array', 'max:100'],
            'widgets.*.key' => ['required', 'string', Rule::in(DashboardWidgetRegistry::keys())],
            'widgets.*.position' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $widgets = $this->input('widgets');

            if (! is_array($widgets)) {
                return;
            }

            $this->rejectDuplicates($validator, $widgets);
            $this->rejectUngrantedWidgets($validator, $widgets);
        });
    }

    /**
     * @param  array<int, mixed>  $widgets
     */
    private function rejectDuplicates(Validator $validator, array $widgets): void
    {
        $seen = [];

        foreach ($widgets as $index => $widget) {
            $key = is_array($widget) ? ($widget['key'] ?? null) : null;

            if (! is_string($key)) {
                continue;
            }

            if (isset($seen[$key])) {
                $validator->errors()->add("widgets.{$index}.key", 'Ce widget est déjà configuré.');
            }

            $seen[$key] = true;
        }
    }

    /**
     * Refuse les widgets que le rôle n'a pas le droit de voir.
     *
     * Les permissions du **rôle**, pas celles de l'appelant : un propriétaire
     * d'organisme les détient toutes, et se servir des siennes ici aurait
     * accepté n'importe quel widget pour n'importe quel rôle.
     *
     * @param  array<int, mixed>  $widgets
     */
    private function rejectUngrantedWidgets(Validator $validator, array $widgets): void
    {
        $role = $this->route('role');

        if ($role === null) {
            return;
        }

        $granted = $role->permissions()->pluck('code')->all();

        foreach ($widgets as $index => $widget) {
            $key = is_array($widget) ? ($widget['key'] ?? null) : null;
            $definition = is_string($key) ? DashboardWidgetRegistry::find($key) : null;

            if ($definition === null || in_array($definition->requiredPermission, $granted, true)) {
                continue;
            }

            $validator->errors()->add(
                "widgets.{$index}.key",
                "Ce rôle n'a pas la permission requise : {$definition->requiredPermission}.",
            );
        }
    }
}
