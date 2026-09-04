import type {
  CustomerApiConfigurationFilters,
  CustomerImportConfigurationFilters,
} from '../types/customerIntegration'

/**
 * Clés de cache des intégrations client.
 *
 * **Aucune clé ne porte de clé API.** `create` et `rotate-key` renvoient un
 * secret en clair ; leur résultat ne passe jamais par le cache, il va
 * directement au dialogue qui l'affiche. Une entrée de cache survivrait à la
 * fermeture du dialogue, ce que le §22 interdit.
 *
 * Les exports gardent leurs propres clés dans `modules/exports` : la Phase 6 les
 * a posées, et en créer un second jeu ferait exactement les deux
 * implémentations concurrentes que le §77 proscrit.
 */
export const integrationKeys = {
  all: ['integrations'] as const,

  imports: () => [...integrationKeys.all, 'imports'] as const,
  importList: (filters: CustomerImportConfigurationFilters) =>
    [...integrationKeys.imports(), 'list', filters] as const,
  importsOfCustomer: (customerId: string, filters: CustomerImportConfigurationFilters) =>
    [...integrationKeys.imports(), 'customer', customerId, filters] as const,
  import: (id: string) => [...integrationKeys.imports(), 'detail', id] as const,

  apiAccess: () => [...integrationKeys.all, 'api-access'] as const,
  apiAccessList: (filters: CustomerApiConfigurationFilters) =>
    [...integrationKeys.apiAccess(), 'list', filters] as const,
  apiAccessOfCustomer: (customerId: string, filters: CustomerApiConfigurationFilters) =>
    [...integrationKeys.apiAccess(), 'customer', customerId, filters] as const,
  apiAccessDetail: (id: string) => [...integrationKeys.apiAccess(), 'detail', id] as const,
}
