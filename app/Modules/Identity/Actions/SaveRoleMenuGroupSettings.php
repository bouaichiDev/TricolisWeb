<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\RoleMenuGroup;

/**
 * Applique à un groupe créé les réglages venus de `PATCH /roles/{role}/menu`.
 *
 * Un groupe créé se règle par le même écran et le même enregistrement que les
 * entrées du catalogue — on ne compose pas un menu en deux fois. Mais ses
 * valeurs vivent dans **sa propre ligne**, et non dans `role_menu_items` : il
 * n'a pas de valeur de catalogue à surcharger, il *est* sa propre valeur. Les
 * ranger avec les surcharges donnerait deux endroits où lire son nom, et l'un
 * des deux finirait par mentir.
 */
final readonly class SaveRoleMenuGroupSettings
{
    /**
     * @param  array<string, mixed>  $item  Une entrée de la charge utile validée.
     */
    public function apply(string $roleId, array $item): void
    {
        $group = RoleMenuGroup::where('role_id', $roleId)
            ->where('code', $item['code'])
            ->first();

        if ($group === null) {
            return;
        }

        $values = ['is_visible' => $item['isVisible'] ?? true];

        if (array_key_exists('position', $item)) {
            $values['position'] = $item['position'];
        }

        // Vider le nom d'un groupe créé n'a pas de sens : il n'a pas de libellé
        // livré vers lequel revenir, et son titre serait vide dans la barre
        // latérale. La demande est ignorée plutôt que refusée, comme l'est le
        // masquage d'une entrée qui doit rester visible.
        foreach (['label', 'icon'] as $field) {
            $value = $this->trimmed($item[$field] ?? null);

            if ($value !== null) {
                $values[$field] = $value;
            }
        }

        $group->update($values);
    }

    private function trimmed(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
