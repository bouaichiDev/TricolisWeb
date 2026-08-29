<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Actions;

use App\Modules\Organizations\Models\OrganizationMenuItem;
use App\Shared\Enums\RoleScope;
use App\Shared\Menu\MenuCatalogue;
use App\Shared\Menu\MenuEntry;

/**
 * Donne à une organisation une ligne pour chaque entrée du catalogue.
 *
 * Deux exigences se rencontrent ici, et elles tirent en sens contraire :
 *
 * - **l'administrateur doit voir le menu de base** dans l'écran de réglage, ce
 *   qui suppose des lignes existantes ;
 * - **une entrée ajoutée à une phase suivante doit parvenir aux organisations
 *   déjà créées**, ce qu'un instantané figé empêcherait.
 *
 * La conciliation tient en une règle : cette action **crée les lignes
 * manquantes et ne touche jamais aux existantes**. Elle est donc rejouable, et
 * une phase qui enrichit le catalogue se propage en la rejouant — sans écraser
 * ce qu'une organisation a choisi de masquer.
 *
 * Le repli du résolveur est conservé : une entrée sans ligne reste visible.
 * C'est le filet de sécurité si la synchronisation est oubliée après une phase
 * — mieux vaut une entrée de trop qu'un écran devenu inatteignable.
 *
 * **Le réalignement des positions se demande explicitement.** Une phase qui
 * insère des entrées au milieu d'une section laisse les lignes existantes sur
 * leurs anciens rangs, et l'ordre affiché ne suit plus le catalogue. Remettre
 * les rangs d'office effacerait pourtant l'ordre qu'une organisation s'est
 * choisi : c'est donc une décision, pas un effet de bord.
 */
final readonly class SyncOrganizationMenu
{
    /**
     * @return int Nombre de lignes créées ou repositionnées.
     */
    public function execute(string $organizationId, bool $reposition = false): int
    {
        $existing = OrganizationMenuItem::where('organization_id', $organizationId)
            ->pluck('code')
            ->all();

        $created = 0;

        foreach (MenuCatalogue::forScope(RoleScope::ORGANIZATION) as $entry) {
            if (in_array($entry->code, $existing, true)) {
                if ($reposition) {
                    $created += $this->reposition($organizationId, $entry);
                }

                continue;
            }

            $this->create($organizationId, $entry);
            $created++;
        }

        return $created;
    }

    /**
     * Les entrées plateforme sont exclues : elles n'appartiennent à aucune
     * organisation, et leur donner une ligne laisserait croire qu'un organisme
     * peut les régler.
     */
    private function create(string $organizationId, MenuEntry $entry): void
    {
        OrganizationMenuItem::create([
            'organization_id' => $organizationId,
            'code' => $entry->code,
            'is_visible' => true,
            'position' => $entry->position,
        ]);
    }

    /**
     * Remet une ligne existante au rang du catalogue.
     *
     * La visibilité n'est jamais touchée : une entrée masquée le reste, seul
     * son rang change.
     *
     * @return int 1 si le rang a bougé, 0 sinon
     */
    private function reposition(string $organizationId, MenuEntry $entry): int
    {
        return OrganizationMenuItem::where('organization_id', $organizationId)
            ->where('code', $entry->code)
            ->where('position', '!=', $entry->position)
            ->update(['position' => $entry->position]);
    }
}
