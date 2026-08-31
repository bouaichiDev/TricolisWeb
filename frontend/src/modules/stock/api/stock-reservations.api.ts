import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type { StockReservation, StockReservationDetail } from '../types/stock'
import type { StockReservationFilters } from '../types/stockFilters'

/** Charge utile relevée sur `StoreStockReservationRequest`. */
export interface StockReservationPayload {
  stockItemId: string
  stockLocationId: string
  orderLineId: string
  quantity: number
  status: string
}

/**
 * `UpdateStockReservationRequest` n'accepte **que** `status`.
 *
 * Ni la quantité, ni l'emplacement, ni la ligne de commande : changer l'un des
 * trois reviendrait à déplacer une quantité réservée sans mouvement, ce que le
 * §5 interdit. Une réservation dont la quantité doit changer se libère, et une
 * autre se crée.
 */
export interface StockReservationStatusPayload {
  status: string
}

export const stockReservationsApi = {
  list: (filters: StockReservationFilters) =>
    api.get<ApiCollection<StockReservation>>('/stock-reservations', { query: { ...filters } }),

  get: (id: string) =>
    api
      .get<ApiResource<StockReservationDetail>>(`/stock-reservations/${id}`)
      .then((response) => response.data),

  /** 409 si le disponible ne couvre pas la quantité, sous verrou. */
  create: (payload: StockReservationPayload) =>
    api
      .post<ApiResource<StockReservationDetail>>('/stock-reservations', payload)
      .then((response) => response.data),

  updateStatus: (id: string, payload: StockReservationStatusPayload) =>
    api
      .patch<ApiResource<StockReservationDetail>>(`/stock-reservations/${id}`, payload)
      .then((response) => response.data),

  /**
   * Libération : une action, pas une suppression.
   *
   * `ReleaseStockReservationAction` renseigne `releasedAt`, écrit le statut et
   * rend la quantité au disponible — le tout sous verrou. La réservation reste
   * en base : c'est la trace de ce qui a été promis puis rendu. Une seconde
   * libération sort en 409, contrôlée deux fois, avant et sous verrou.
   */
  release: (id: string, payload: StockReservationStatusPayload) =>
    api
      .post<ApiResource<StockReservationDetail>>(`/stock-reservations/${id}/release`, payload)
      .then((response) => response.data),
}
