import { api } from '@/shared/api/client'
import type { ApiEnvelope, Paginated } from '@/shared/api/types'

import type { StockAdjustmentPayload, StockAdjustmentRequest } from '../adjustmentTypes'

/**
 * Demandes de correction de stock : déposer et lire. Aucune décision ne se
 * prend depuis le portail — le transporteur les tranche en interne.
 */
export const adjustmentsApi = {
  list: (filters: { stockItemId?: string; page?: number; perPage?: number }) =>
    api.get<Paginated<StockAdjustmentRequest>>('/stock/adjustment-requests', { query: { ...filters, perPage: filters.perPage ?? 20 } }),
  create: (payload: StockAdjustmentPayload) =>
    api.post<ApiEnvelope<StockAdjustmentRequest>>('/stock/adjustment-requests', payload).then((response) => response.data),
}
