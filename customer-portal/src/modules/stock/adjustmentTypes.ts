import type { StatusView } from '@/modules/orders/types'

import type { StockLocation } from './types'

/** Une demande de correction du client, et la réponse du transporteur. */
export interface StockAdjustmentRequest {
  id: string
  stockItem: { id: string; articleCode: string; description: string | null }
  location: StockLocation | null
  /** Ce que le portail affichait au moment de la demande, figé par le backend. */
  displayedQuantity: string
  requestedQuantity: string
  /** Constaté moins affiché, calculé au backend. */
  difference: string
  reason: string
  comment: string | null
  status: StatusView
  isPending: boolean
  reviewComment: string | null
  reviewedAt: string | null
  createdAt: string | null
}

/** Ce que le client déclare ; la quantité affichée et le statut sont posés par le backend. */
export interface StockAdjustmentPayload {
  stockItemId: string
  stockLocationId?: string | null
  requestedQuantity: string
  reason: string
  comment?: string | null
}
