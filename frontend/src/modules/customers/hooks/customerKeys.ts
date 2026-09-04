import type { CustomerFilters } from '../types/customer'

/**
 * Fabrique de cles de requete (§28).
 *
 * La hierarchie permet d'invalider par niveau : `lists()` apres une creation,
 * `detail(id)` apres une modification. Invalider `all` a chaque ecriture
 * rechargerait des donnees inchangees.
 */
export const customerKeys = {
  all: ['customers'] as const,
  lists: () => [...customerKeys.all, 'list'] as const,
  list: (filters: CustomerFilters) => [...customerKeys.lists(), filters] as const,
  details: () => [...customerKeys.all, 'detail'] as const,
  detail: (id: string) => [...customerKeys.details(), id] as const,
}
