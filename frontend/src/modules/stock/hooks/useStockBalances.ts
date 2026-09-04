import { useQuery } from '@tanstack/react-query'

import { stockBalancesApi } from '../api/stock-balances.api'
import { stockKeys } from './stockKeys'
import type { StockBalanceFilters } from '../types/stockFilters'

/**
 * Soldes de stock — **lecture seule**, sans exception.
 *
 * Aucun hook de mutation n'existe ici. Une quantité ne se corrige pas : elle se
 * déplace par un mouvement, ou se réserve. Un solde écrit à la main n'aurait
 * aucune histoire, et deux corrections concurrentes s'écraseraient sans que
 * rien ne le signale.
 */
export function useStockBalances(filters: StockBalanceFilters, enabled = true) {
  return useQuery({
    queryKey: stockKeys.balanceList(filters),
    queryFn: () => stockBalancesApi.list(filters),
    enabled,
    placeholderData: (previous) => previous,
  })
}

export function useCustomerStockBalances(
  customerId: string,
  filters: StockBalanceFilters,
  enabled = true,
) {
  return useQuery({
    queryKey: stockKeys.balancesOfCustomer(customerId, filters),
    queryFn: () => stockBalancesApi.byCustomer(customerId, filters),
    enabled: enabled && customerId !== '',
    placeholderData: (previous) => previous,
  })
}
