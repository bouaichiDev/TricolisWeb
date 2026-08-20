import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { stockApi, type StockLocationPayload } from '../api/stock.api'
import { stockKeys } from './stockKeys'

/** Un emplacement créé, modifié ou supprimé change la liste, jamais les soldes. */
function useInvalidateLocations() {
  const queryClient = useQueryClient()

  return () => void queryClient.invalidateQueries({ queryKey: stockKeys.locations() })
}

export function useCreateStockLocation() {
  const invalidate = useInvalidateLocations()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: StockLocationPayload) => stockApi.createLocation(payload),
    onSuccess: () => {
      invalidate()
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateStockLocation() {
  const invalidate = useInvalidateLocations()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: ({ id, ...payload }: Partial<StockLocationPayload> & { id: string }) =>
      stockApi.updateLocation(id, payload),
    onSuccess: () => {
      invalidate()
      toast.success(t('toast.updated'))
    },
  })
}

/**
 * Suppression d'un emplacement.
 *
 * `stock_balances.stock_location_id` est en `restrictOnDelete` : un emplacement
 * qui porte encore du stock est refusé par le serveur, en 409. C'est voulu —
 * l'effacer ferait disparaître des quantités réelles.
 */
export function useDeleteStockLocation() {
  const invalidate = useInvalidateLocations()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => stockApi.removeLocation(id),
    onSuccess: () => {
      invalidate()
      toast.success(t('toast.deleted'))
    },
  })
}
