/**
 * Le glisser-déposer de la planification.
 *
 * Il s'appuie sur le DnD natif du navigateur plutôt que sur une bibliothèque :
 * ce qu'on déplace ici est toujours la même chose — une commande ou un service
 * vers une tournée brouillon — et le §113 demande expressément de **ne pas**
 * ouvrir un glisser libre sous prétexte qu'un composant le permettrait.
 *
 * Le type MIME est propre à l'application : un fichier ou un texte glissé depuis
 * le bureau ne doit pas être pris pour une commande. Pendant le survol, le
 * navigateur interdit de lire les données — seuls les **types** sont visibles,
 * d'où ce marqueur qui suffit à décider si la cible accepte.
 */
export const PLANNING_DRAG_TYPE = 'application/x-tricolis-planning'

export interface PlanningDragPayload {
  /** Une commande entière, ou l'un de ses services seulement. */
  kind: 'order' | 'service'
  id: string
  /** Montré pendant le survol : le planificateur doit savoir ce qu'il tient. */
  label: string
}

export function startPlanningDrag(
  event: React.DragEvent,
  payload: PlanningDragPayload,
): void {
  event.dataTransfer.setData(PLANNING_DRAG_TYPE, JSON.stringify(payload))
  event.dataTransfer.effectAllowed = 'copy'
  // Sans cette seconde écriture, Firefox refuse de démarrer le glisser.
  event.dataTransfer.setData('text/plain', payload.label)
}

/** La cible accepte-t-elle ce glisser ? Seuls les types sont lisibles au survol. */
export function carriesPlanningDrag(event: React.DragEvent): boolean {
  return Array.from(event.dataTransfer.types).includes(PLANNING_DRAG_TYPE)
}

/**
 * Ce qui vient d'être déposé, ou `null`.
 *
 * Un contenu illégible n'est pas une erreur à signaler : c'est un glisser qui
 * ne nous concerne pas, et le dépôt doit simplement ne rien faire.
 */
export function readPlanningDrag(event: React.DragEvent): PlanningDragPayload | null {
  const raw = event.dataTransfer.getData(PLANNING_DRAG_TYPE)

  if (raw === '') return null

  try {
    const parsed = JSON.parse(raw) as PlanningDragPayload

    return parsed.kind === 'order' || parsed.kind === 'service' ? parsed : null
  } catch {
    return null
  }
}

/** Traduit un dépôt en corps de mutation, tel que `POST /tours/{id}/plan` l'attend. */
export function planPayloadOf(drag: PlanningDragPayload): {
  orderIds?: string[]
  orderServiceIds?: string[]
} {
  return drag.kind === 'order' ? { orderIds: [drag.id] } : { orderServiceIds: [drag.id] }
}
