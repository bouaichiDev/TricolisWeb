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
 * 1. **Un brouillon n'appartient qu'à son créateur.** Un autre l'ouvre en
 *    lecture seule, avec le nom de celui qui le tient — de quoi le lui demander.
 *    Le serveur applique déjà cette règle ; l'écran cesse simplement de
 *    promettre ce qu'il refuserait.
 * 2. **Un brouillon qui porte des arrêts attend une conclusion.** C'est un plan
 *    commencé : il se valide ou s'annule, et l'exclusivité ne cesse qu'alors.
 * 3. **Une tournée à la fois.** Tant que celle qu'on regarde attend sa
 *    conclusion, les autres sont fermées *dans la carte* — deux plans ouverts
 *    en parallèle sur un même fond se confondent. La vue en colonnes, elle,
 *    reste libre : c'est là qu'on corrige une tournée réelle.
 *
 * L'exclusivité n'est pas un verrou posé quelque part — le §20 interdit une
 * table `PlanningLock`, le §23 une colonne `lockedBy`. Elle se déduit du
 * créateur, et cesse d'elle-même quand la tournée quitte le brouillon. Rien ne
 * peut rester coincé.
 */
export function usePlanningSession(currentUserId: string | null) {
  /** Le brouillon est-il tenu par quelqu'un d'autre ? */
  const heldByOther = (tour: Tour): boolean =>
    tour.status === 'draft' &&
    tour.plannedBy !== null &&
    tour.plannedBy !== undefined &&
    tour.plannedBy.id !== currentUserId

  /**
   * Ce brouillon est-il un plan commencé, en attente d'être conclu ?
   *
   * Un brouillon vide n'attend rien : il n'y a ni à valider ni à annuler.
   */
  const awaitsConclusion = (tour: Tour): boolean =>
    tour.status === 'draft' && !heldByOther(tour) && (tour.stops ?? []).length > 0

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
