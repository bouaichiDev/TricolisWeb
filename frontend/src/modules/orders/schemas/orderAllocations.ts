import type { OrderLine, OrderPackage } from '../types/orderDetail'

export interface LineUsage {
  ordered: number
  assigned: number
  remaining: number
  over: boolean
}

const toNumber = (value: number | string | null | undefined): number => {
  if (value === null || value === undefined) return 0

  const parsed = Number(value)

  return Number.isFinite(parsed) ? parsed : 0
}

/**
 * Quantités commandées, affectées et restantes sur une commande enregistrée.
 *
 * Pendant dans la fiche de ce que `lineAllocations` calcule dans le brouillon.
 * La donnée est différente — des ressources d'API plutôt qu'un état de
 * formulaire — mais la règle est la même : `PackageLineAllocator` refuse qu'une
 * ligne soit affectée au-delà de sa quantité commandée, et l'écran doit le
 * montrer avant l'envoi plutôt qu'après le refus.
 */
export function orderLineUsage(
  lines: OrderLine[],
  packages: OrderPackage[],
): Map<string, LineUsage> {
  const assigned = new Map<string, number>()

  for (const item of packages) {
    for (const link of item.lines ?? []) {
      assigned.set(link.orderLineId, (assigned.get(link.orderLineId) ?? 0) + toNumber(link.quantity))
    }
  }

  return new Map(
    lines.map((line) => {
      const ordered = toNumber(line.quantity)
      const used = assigned.get(line.id) ?? 0

      return [line.id, { ordered, assigned: used, remaining: ordered - used, over: used > ordered }]
    }),
  )
}

/** Formatage court d'une quantité : pas de zéros décimaux inutiles. */
export function formatAmount(value: number): string {
  return Number.isInteger(value) ? String(value) : String(Number(value.toFixed(3)))
}
