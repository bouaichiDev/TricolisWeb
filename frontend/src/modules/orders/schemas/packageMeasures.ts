import type { OrderDraft, ServiceDraft } from './orderDraft'

export interface Measures {
  weight: number
  volume: number
}

/** Ce que transporte un service : déduit de ses colis, jamais saisi. */
export interface ServiceContent extends Measures {
  packageCount: number
}

const toNumber = (value: string): number => {
  const parsed = Number(value.trim())

  return Number.isFinite(parsed) ? parsed : 0
}

/** Mêmes précisions que les colonnes : `decimal(12,3)` et `decimal(12,4)`. */
const rounded = (measures: Measures): Measures => ({
  weight: Number(measures.weight.toFixed(3)),
  volume: Number(measures.volume.toFixed(4)),
})

/**
 * Poids et volume de chaque colis du brouillon.
 *
 * Un colis pèse ce qu'il contient : les lignes qui lui sont affectées — poids
 * unitaire × quantité affectée, comme `RecalculateOrderTotals` le fait pour la
 * commande — plus ses colis enfants. Une palette pèse donc ses cartons.
 *
 * Un cycle de parenté n'est pas censé exister ; s'il existait, le colis déjà en
 * cours de calcul compte pour zéro plutôt que de boucler.
 */
export function packageMeasures(draft: Pick<OrderDraft, 'lines' | 'packages'>): Map<string, Measures> {
  const lines = new Map(draft.lines.map((line) => [line.key, line]))
  const byKey = new Map(draft.packages.map((item) => [item.key, item]))
  const result = new Map<string, Measures>()
  const visiting = new Set<string>()

  const measure = (key: string): Measures => {
    const known = result.get(key)
    if (known !== undefined) return known

    const item = byKey.get(key)
    if (item === undefined || visiting.has(key)) return { weight: 0, volume: 0 }
    visiting.add(key)

    let weight = 0
    let volume = 0

    for (const link of item.lines) {
      const line = lines.get(link.lineKey)
      if (line === undefined) continue

      const quantity = toNumber(link.quantity)
      weight += toNumber(line.weight) * quantity
      volume += toNumber(line.volume) * quantity
    }

    for (const child of draft.packages) {
      if (child.parentKey !== key) continue

      const inner = measure(child.key)
      weight += inner.weight
      volume += inner.volume
    }

    const measures = rounded({ weight, volume })
    result.set(key, measures)
    visiting.delete(key)

    return measures
  }

  for (const item of draft.packages) measure(item.key)

  return result
}

/**
 * Contenu d'un service, déduit des colis qu'il prend en charge.
 *
 * `packageCount` compte les colis cochés. Le poids, lui, ne compte pas deux
 * fois un carton dont la palette est aussi cochée : la palette le contient
 * déjà.
 */
export function serviceContent(
  service: Pick<ServiceDraft, 'packages'>,
  draft: Pick<OrderDraft, 'lines' | 'packages'>,
  measures: Map<string, Measures> = packageMeasures(draft),
): ServiceContent {
  const parents = new Map(draft.packages.map((item) => [item.key, item.parentKey]))
  const linked = new Set(
    service.packages.map((link) => link.packageKey).filter((key) => parents.has(key)),
  )

  const insideLinked = (key: string): boolean => {
    const seen = new Set<string>()
    let parent = parents.get(key) ?? null

    while (parent !== null && !seen.has(parent)) {
      if (linked.has(parent)) return true
      seen.add(parent)
      parent = parents.get(parent) ?? null
    }

    return false
  }

  let weight = 0
  let volume = 0

  for (const key of linked) {
    if (insideLinked(key)) continue

    const item = measures.get(key)
    weight += item?.weight ?? 0
    volume += item?.volume ?? 0
  }

  return { ...rounded({ weight, volume }), packageCount: linked.size }
}
