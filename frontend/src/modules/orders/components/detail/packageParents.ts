import type { OrderPackage } from '../../types/orderDetail'

export const packageDisplayName = (item: OrderPackage): string =>
  item.reference ?? item.barcode ?? item.id

/**
 * Colis proposables comme parent d'un colis donné.
 *
 * Un colis ne peut devenir ni son propre parent ni celui d'un de ses
 * descendants : le cycle serait refusé par le serveur, et le proposer ferait
 * découvrir la règle après coup. La descendance se calcule par propagation
 * depuis la liste plate, faute d'arbre en mémoire à cet endroit.
 */
export function assignableParents(
  packages: OrderPackage[],
  pkg: OrderPackage | null,
): OrderPackage[] {
  if (pkg === null) return packages

  const forbidden = new Set([pkg.id])
  let added = true

  while (added) {
    added = false

    for (const item of packages) {
      if (item.parentPackageId && forbidden.has(item.parentPackageId) && !forbidden.has(item.id)) {
        forbidden.add(item.id)
        added = true
      }
    }
  }

  return packages.filter((item) => !forbidden.has(item.id))
}
