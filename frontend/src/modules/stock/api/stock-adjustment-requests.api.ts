import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type {
  StockAdjustmentRequest,
  StockAdjustmentRequestDetail,
  StockAdjustmentRequestFilters,
} from '../types/stockAdjustment'

/**
 * `stockLocationId` n'est attendu que pour une demande « tout l'article », et
 * refusé sinon (422) : la règle vit dans l'Action serveur.
 */
export interface ApproveAdjustmentPayload {
  stockLocationId?: string
  reviewComment?: string
}

/** La réponse est obligatoire au refus (3 à 2000 caractères). */
export interface RejectAdjustmentPayload {
  reviewComment: string
}

/**
 * Demandes de correction déposées au portail client.
 *
 * Ni création, ni modification, ni suppression : une demande naît chez le
 * client et ne se rejoue pas. Une décision déjà rendue répond 409.
 */
export const stockAdjustmentRequestsApi = {
  list: (filters: StockAdjustmentRequestFilters) =>
    api.get<ApiCollection<StockAdjustmentRequest>>('/stock-adjustment-requests', {
      query: { ...filters },
    }),

  ofCustomer: (customerId: string, filters: StockAdjustmentRequestFilters) =>
    api.get<ApiCollection<StockAdjustmentRequest>>(
      `/customers/${customerId}/stock-adjustment-requests`,
      { query: { ...filters } },
    ),

  get: (id: string) =>
    api
      .get<ApiResource<StockAdjustmentRequestDetail>>(`/stock-adjustment-requests/${id}`)
      .then((response) => response.data),

  approve: (id: string, payload: ApproveAdjustmentPayload) =>
    api
      .post<ApiResource<StockAdjustmentRequestDetail>>(
        `/stock-adjustment-requests/${id}/approve`,
        payload,
      )
      .then((response) => response.data),

  reject: (id: string, payload: RejectAdjustmentPayload) =>
    api
      .post<ApiResource<StockAdjustmentRequestDetail>>(
        `/stock-adjustment-requests/${id}/reject`,
        payload,
      )
      .then((response) => response.data),
}
