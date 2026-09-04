import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type { StockBalance, StockBalanceDetail } from '../types/stock'
import type { StockBalanceFilters } from '../types/stockFilters'

/**
 * Soldes de stock — **lecture seule**.
 *
 * Aucune méthode d'écriture n'existe ici, et ce n'est pas un oubli :
 * `StockBalancePolicy` n'expose que `viewAny` et `view`, et aucune route ne
 * permet de créer, modifier ou supprimer un solde. Une quantité se déplace par
 * un mouvement, se réserve par une réservation ; le solde en découle.
 */
export const stockBalancesApi = {
  list: (filters: StockBalanceFilters) =>
    api.get<ApiCollection<StockBalance>>('/stock-balances', { query: { ...filters } }),

  byCustomer: (customerId: string, filters: StockBalanceFilters) =>
    api.get<ApiCollection<StockBalance>>(`/customers/${customerId}/stock-balances`, {
      query: { ...filters },
    }),

  get: (id: string) =>
    api
      .get<ApiResource<StockBalanceDetail>>(`/stock-balances/${id}`)
      .then((response) => response.data),
}
