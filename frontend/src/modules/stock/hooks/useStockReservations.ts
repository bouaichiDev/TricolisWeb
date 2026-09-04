import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { orderKeys } from '@/modules/orders/hooks/useOrders'
import { ApiError } from '@/shared/api/errors'

import {
  stockReservationsApi,
  type StockReservationPayload,
  type StockReservationStatusPayload,
} from '../api/stock-reservations.api'
import { stockKeys } from './stockKeys'
import type { StockReservationFilters } from '../types/stockFilters'

export function useStockReservations(filters: StockReservationFilters, enabled = true) {
  return useQuery({
    queryKey: stockKeys.reservationList(filters),
    queryFn: () => stockReservationsApi.list(filters),
    enabled,
    placeholderData: (previous) => previous,
  })
}

export function useStockReservation(id: string | undefined) {
  return useQuery({
    queryKey: stockKeys.reservation(id ?? ''),
    queryFn: () => stockReservationsApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

/**
 * Ce qu'une écriture de réservation périme.
 *
 * Réserver déplace une quantité du disponible vers le réservé : le solde change,
 * la fiche de l'article aussi, et `OrderLine.reservedQuantity` est calculé par
 * le serveur à partir des réservations — la commande doit donc être relue. Ne
 * rafraîchir que la liste des réservations laisserait trois écrans afficher des
 * chiffres qui ne s'additionnent plus.
 */
function useInvalidateReservation() {
  const queryClient = useQueryClient()

  return () => {
    void queryClient.invalidateQueries({ queryKey: stockKeys.reservations() })
    void queryClient.invalidateQueries({ queryKey: stockKeys.balances() })
    void queryClient.invalidateQueries({ queryKey: stockKeys.items() })
    void queryClient.invalidateQueries({ queryKey: orderKeys.all })
  }
}

/**
 * Crée une réservation.
 *
 * `CreateStockReservationAction` verrouille le solde, vérifie le disponible,
 * écrit la réservation puis recalcule — dans une transaction. Deux sessions qui
 * réservent en même temps ne peuvent donc pas dépasser le disponible : la
 * seconde reçoit un 409, et l'écran relit les soldes plutôt que de laisser
 * réessayer sur un chiffre périmé.
 */
export function useCreateStockReservation() {
  const invalidate = useInvalidateReservation()
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: StockReservationPayload) => stockReservationsApi.create(payload),
    onSuccess: () => {
      invalidate()
      toast.success(t('stock.reservationCreated'))
    },
    // Le message est affiché par le formulaire appelant ; ce qui se fait ici est
    // de relire les soldes, dont la valeur affichée vient d'être démentie.
    onError: (error) => {
      if (error instanceof ApiError && error.status === 409) {
        void queryClient.invalidateQueries({ queryKey: stockKeys.balances() })
      }
    },
  })
}

/** Seul le statut est modifiable : ni quantité, ni emplacement, ni ligne. */
export function useUpdateStockReservationStatus(id: string) {
  const invalidate = useInvalidateReservation()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: StockReservationStatusPayload) =>
      stockReservationsApi.updateStatus(id, payload),
    onSuccess: () => {
      invalidate()
      toast.success(t('toast.updated'))
    },
  })
}

/**
 * Libère une réservation.
 *
 * La réservation **reste en base** : `releasedAt` est renseignée, le statut
 * change, et la quantité repart au disponible. La supprimer effacerait la trace
 * de ce qui avait été promis.
 *
 * Une seconde libération est refusée par le serveur, deux fois — avant la
 * transaction et sous verrou. Le bouton est masqué quand `releasedAt` existe,
 * mais c'est une commodité d'affichage : deux onglets ouverts suffisent à la
 * contourner, et c'est le 409 qui tranche.
 */
export function useReleaseStockReservation(id: string) {
  const invalidate = useInvalidateReservation()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: StockReservationStatusPayload) =>
      stockReservationsApi.release(id, payload),
    onSuccess: () => {
      invalidate()
      toast.success(t('stock.reservationReleased'))
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : t('errors.unexpected'))
    },
  })
}
