import type { OrderPackage } from '../../types/orderDetail'

/**
 * Dimensions d'un colis, sur une ligne : `L × l × H`.
 *
 * La maquette les réunit en une colonne. Les trois champs existent séparément
 * en base ; les afficher en trois colonnes dans un tableau qui en compte déjà
 * six aurait rendu la ligne illisible.
 *
 * Un colis sans aucune dimension renvoie `null` — un « — » vaut mieux que
 * « — × — × — ».
 */
export function packageDimensions(pkg: OrderPackage): string | null {
  const parts = [pkg.length, pkg.width, pkg.height]

  if (parts.every((part) => part === null)) return null

  return parts.map((part) => (part === null ? '—' : String(part))).join(' × ')
}
