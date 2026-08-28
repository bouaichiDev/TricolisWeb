import type { Tour } from '@/modules/tours/types/tour'

/**
 * Ce qu'un planificateur a le droit de faire, dans la carte.
 *
 * **Tout se déduit des données, rien n'est retenu en mémoire.** Une première
 * version gardait « la tournée que j'ai touchée » dans un état de composant :
 * fermer la fenêtre l'effaçait, et rouvrir la tournée ne proposait plus de
 * conclure — alors qu'elle était toujours réservée. Une réservation qui
 * disparaît en fermant un panneau n'en est pas une.
 *
 * Les trois règles, issues des §22 à §26 :
 *
 * 1. **Une tournée réservée n'appartient qu'à celui qui la compose.** Un autre
 *    l'ouvre en lecture seule, avec son nom — de quoi le lui demander. Le
 *    serveur applique déjà cette règle ; l'écran cesse simplement de promettre
 *    ce qu'il refuserait.
 * 2. **Une tournée réservée attend d'être rendue.** La réservation se prend au
 *    premier geste, côté serveur, et survit à la fermeture de la fenêtre : c'est
 *    elle, et non une variable d'écran, qui dit qu'une composition est en cours.
 * 3. **Une tournée à la fois.** Tant que celle qu'on regarde attend sa
 *    conclusion, les autres sont fermées *dans la carte* — deux plans ouverts
 *    en parallèle sur un même fond se confondent. La vue en colonnes, elle,
 *    reste libre : c'est là qu'on corrige une tournée réelle.
 *
 * La réservation est **deux colonnes sur la tournée**, pas la table
 * `PlanningLock` que le §20 interdit. Le §23 n'en refusait l'ajout que « sans
 * validation de conception » : elle est venue le 28 août 2026, avec l'exigence
 * que confirmer ses modifications ne touche pas au statut — ce qui ôtait au
 * statut sa dernière façon de dire qu'une composition était finie.
 */
export function usePlanningSession(currentUserId: string | null) {
  /**
   * Le brouillon est-il tenu par quelqu'un d'autre ?
   *
   * La réservation vient du serveur — `lockedBy` — et non plus du créateur :
   * elle se prend au premier geste et se rend quand on a fini, sans que le
   * statut bouge. Le créateur, lui, reste affiché à part.
   */
  const heldByOther = (tour: Tour): boolean =>
    tour.status === 'draft' &&
    tour.lockedBy !== null &&
    tour.lockedBy !== undefined &&
    tour.lockedBy.id !== currentUserId

  /**
   * Cette tournée est-elle réservée par moi, donc à rendre ?
   *
   * Une tournée libre n'attend rien : il n'y a ni à confirmer ni à abandonner.
   */
  const awaitsConclusion = (tour: Tour): boolean =>
    tour.status === 'draft' &&
    !heldByOther(tour) &&
    tour.lockedBy !== null &&
    tour.lockedBy !== undefined

  return {
    heldByOther,
    awaitsConclusion,

    /**
     * Peut-on verser dans cette tournée ?
     *
     * Il faut qu'elle soit au brouillon, qu'un autre ne la tienne pas, et
     * qu'aucune autre tournée commencée ne soit ouverte à côté.
     */
    canReceive: (tour: Tour, openTour: Tour | null): boolean => {
      if (tour.status !== 'draft' || heldByOther(tour)) return false

      return openTour === null || openTour.id === tour.id || !awaitsConclusion(openTour)
    },
  }
}
