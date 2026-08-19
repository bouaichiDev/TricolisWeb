import type { ServiceDraft } from './orderDraft'

const toNumber = (value: string): number | null => {
  const trimmed = value.trim()
  if (trimmed === '') return null

  const parsed = Number(trimmed)

  return Number.isFinite(parsed) ? parsed : null
}

/** Arrondi à deux décimales, sans zéros inutiles : c'est un montant. */
const money = (value: number): string => String(Number(value.toFixed(2)))

/**
 * Totaux dérivés du prix unitaire et de la quantité.
 *
 * Les quatre montants sont `required` côté serveur et le §29 interdit d'y poser
 * `0` en douce. Ils restent donc saisis — mais un total qui n'est pas
 * `unitaire × quantité` est presque toujours une faute de frappe, alors le
 * calcul est proposé.
 *
 * **Le total reste modifiable** : une remise, un forfait ou un arrondi
 * contractuel s'écrivent à la main, et l'écraser à la frappe suivante ferait
 * perdre la valeur voulue. La règle est donc : recalculer quand on touche au
 * prix unitaire ou à la quantité, ne jamais toucher au total qu'on est en train
 * de saisir.
 */
export function withDerivedTotals(
  service: ServiceDraft,
  patch: Partial<ServiceDraft>,
): Partial<ServiceDraft> {
  const touches = (field: keyof ServiceDraft) => field in patch

  if (touches('customerTotalPrice') || touches('providerTotalCost')) return patch

  const next = { ...service, ...patch }
  const quantity = toNumber(next.quantity)

  if (quantity === null) return patch

  const derived: Partial<ServiceDraft> = {}

  if (touches('customerUnitPrice') || touches('quantity')) {
    const unit = toNumber(next.customerUnitPrice)
    if (unit !== null) derived.customerTotalPrice = money(unit * quantity)
  }

  if (touches('providerUnitCost') || touches('quantity')) {
    const unit = toNumber(next.providerUnitCost)
    if (unit !== null) derived.providerTotalCost = money(unit * quantity)
  }

  return { ...patch, ...derived }
}
