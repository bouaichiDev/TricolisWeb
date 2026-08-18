import type { PackageDraft } from './orderDraft'

/**
 * Ordonne les colis pour que chaque parent précède ses enfants.
 *
 * `CreateOrderPackages` construit son index au fil de la boucle : un
 * `parentKey` désignant un colis déclaré plus loin dans le tableau serait
 * introuvable et la liaison serait refusée. Un parcours en profondeur depuis
 * les racines garantit l'ordre attendu.
 *
 * Un colis dont le parent a disparu entre-temps remonte à la racine plutôt que
 * d'être retiré de l'envoi : perdre une donnée saisie en silence est pire
 * qu'une hiérarchie incomplète.
 */
export function orderedPackages(packages: PackageDraft[]): PackageDraft[] {
  const byParent = new Map<string | null, PackageDraft[]>()

  for (const item of packages) {
    const bucket = byParent.get(item.parentKey)
    if (bucket) bucket.push(item)
    else byParent.set(item.parentKey, [item])
  }

  const known = new Set(packages.map((item) => item.key))
  const ordered: PackageDraft[] = []
  const placed = new Set<string>()

  const walk = (parentKey: string | null) => {
    for (const item of byParent.get(parentKey) ?? []) {
      if (placed.has(item.key)) continue
      placed.add(item.key)
      ordered.push(item)
      walk(item.key)
    }
  }

  walk(null)

  for (const item of packages) {
    if (placed.has(item.key)) continue
    placed.add(item.key)
    ordered.push(known.has(item.parentKey ?? '') ? item : { ...item, parentKey: null })
  }

  return ordered
}

/**
 * Arbre des colis du brouillon, pour l'affichage.
 *
 * Les enfants d'un colis sont regroupés sous lui ; la profondeur sert à
 * l'indentation. C'est la même relation que celle envoyée à l'API, vue
 * autrement.
 */
export interface PackageNode {
  draft: PackageDraft
  depth: number
}

export function packageTree(packages: PackageDraft[]): PackageNode[] {
  const ordered = orderedPackages(packages)
  const depths = new Map<string, number>()
  const nodes: PackageNode[] = []

  for (const draft of ordered) {
    const parentDepth = draft.parentKey === null ? -1 : (depths.get(draft.parentKey) ?? -1)
    const depth = parentDepth + 1
    depths.set(draft.key, depth)
    nodes.push({ draft, depth })
  }

  return nodes
}

/** Descendants d'un colis, utilisé pour supprimer une branche entière. */
export function descendantKeys(packages: PackageDraft[], key: string): string[] {
  const children = packages.filter((item) => item.parentKey === key)

  return children.flatMap((child) => [child.key, ...descendantKeys(packages, child.key)])
}
