import type { OrderDraft } from './orderDraft'

export interface LineAllocation {
  ordered: number
  assigned: number
  remaining: number
  /** Vrai lorsque la somme affectée dépasse la quantité commandée. */
  over: boolean
}

const toNumber = (value: string): number => {
  const parsed = Number(value.trim())

  return Number.isFinite(parsed) ? parsed : 0
}

/**
 * Quantités commandées, affectées et restantes, ligne par ligne.
 *
 * `PackageLineAllocator` refuse qu'une ligne soit affectée au-delà de sa
 * quantité commandée. Le calcul est repris ici pour que le dépassement se voie
 * pendant la saisie plutôt qu'au retour du serveur ; c'est bien le serveur qui
 * tranche, sous verrou, mais l'écran n'a pas à laisser construire une commande
 * qu'il sait invalide.
 */
export function lineAllocations(draft: OrderDraft): Map<string, LineAllocation> {
  const assigned = new Map<string, number>()

  for (const item of draft.packages) {
    for (const link of item.lines) {
      assigned.set(link.lineKey, (assigned.get(link.lineKey) ?? 0) + toNumber(link.quantity))
    }
  }

  return new Map(
    draft.lines.map((line) => {
      const ordered = toNumber(line.quantity)
      const used = assigned.get(line.key) ?? 0

      return [
        line.key,
        { ordered, assigned: used, remaining: ordered - used, over: used > ordered },
      ]
    }),
  )
}

/** Formatage court d'une quantité : pas de zéros décimaux inutiles. */
export function formatQuantity(value: number): string {
  return Number.isInteger(value) ? String(value) : String(Number(value.toFixed(3)))
}
