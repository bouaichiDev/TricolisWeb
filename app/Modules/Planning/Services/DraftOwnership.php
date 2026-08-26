<?php

declare(strict_types=1);

namespace App\Modules\Planning\Services;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Models\Tour;
use App\Shared\Database\MorphMap;

/**
 * À qui appartient une tournée en préparation.
 *
 * Tant qu'une tournée est au brouillon, **seul celui qui l'a créée peut la
 * modifier**. Deux planificateurs qui déplaceraient les mêmes arrêts en même
 * temps produiraient une tournée qu'aucun des deux n'a voulue.
 *
 * **Le créateur n'est pas une colonne.** Le diagramme n'en prévoit pas, et le
 * §23 interdit d'en ajouter une pour l'interface. Il se lit dans le journal
 * d'audit, qui enregistre déjà qui a créé quoi. La réservation est donc une
 * lecture, pas un verrou posé quelque part : rien ne peut rester coincé.
 *
 * L'exclusivité cesse dès que la tournée quitte le brouillon — validée ou
 * annulée. Ensuite, ce sont les permissions ordinaires qui décident.
 */
final readonly class DraftOwnership
{
    /**
     * Identifiant du créateur, ou `null` si le journal ne le dit pas.
     *
     * Une tournée créée par un import ou une commande n'a pas d'utilisateur :
     * personne ne la réserve alors, et les permissions ordinaires suffisent.
     */
    public function creatorIdOf(Tour $tour): ?string
    {
        return AuditLog::where('entity_type', MorphMap::TOUR)
            ->where('entity_id', $tour->id)
            ->where('action', 'tour.created')
            ->orderBy('created_at')
            ->value('user_id');
    }

    /**
     * Créateurs de plusieurs tournées, en une requête.
     *
     * Une liste de tournées ne doit pas poser la question ligne par ligne : le
     * budget de requêtes de la Phase 4 l'a déjà rappelé une fois.
     *
     * @param  list<string>  $tourIds
     * @return array<string, string> identifiant de tournée => identifiant d'utilisateur
     */
    public function creatorsOf(array $tourIds): array
    {
        if ($tourIds === []) {
            return [];
        }

        return AuditLog::where('entity_type', MorphMap::TOUR)
            ->whereIn('entity_id', $tourIds)
            ->where('action', 'tour.created')
            ->orderBy('created_at')
            ->whereNotNull('user_id')
            ->pluck('user_id', 'entity_id')
            ->all();
    }

    /** Le créateur, chargé, quand le journal le nomme. */
    public function creatorOf(Tour $tour): ?User
    {
        $id = $this->creatorIdOf($tour);

        return $id === null ? null : User::find($id);
    }

    /**
     * Cet utilisateur peut-il modifier cette tournée ?
     *
     * Hors brouillon, la question ne se pose pas : les permissions tranchent.
     * Au brouillon, seul le créateur passe — et une tournée sans créateur connu
     * n'est réservée par personne.
     */
    public function canModify(Tour $tour, string $userId): bool
    {
        if ($tour->status !== TourStatus::DRAFT) {
            return true;
        }

        $creatorId = $this->creatorIdOf($tour);

        return $creatorId === null || $creatorId === $userId;
    }
}
