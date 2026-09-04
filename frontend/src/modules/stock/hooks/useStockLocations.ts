import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { ApiError } from '@/shared/api/errors'

import {
  stockLocationsApi,
  type StockLocationPayload,
  type StockLocationUpdatePayload,
} from '../api/stock-locations.api'
import { stockKeys } from './stockKeys'
import type { StockLocationFilters } from '../types/stockFilters'

export function useStockLocations(filters: StockLocationFilters, enabled = true) {
  return useQuery({
    queryKey: stockKeys.locationList(filters),
    queryFn: () => stockLocationsApi.list(filters),
    enabled,
    placeholderData: (previous) => previous,
  })
}

/**
 * Arbre des emplacements d'un dépôt.
 *
 * Non paginé : le serveur renvoie la hiérarchie entière. Sans dépôt, c'est tout
 * le parc qui remonte — utile sur une petite organisation, coûteux sinon. La
 * requête n'est donc lancée que lorsqu'un dépôt est choisi, ou que l'écran le
 * demande explicitement.
 */
export function useStockLocationTree(depotId: string | undefined, enabled = true) {
  return useQuery({
    queryKey: stockKeys.locationTree(depotId),
    queryFn: () => stockLocationsApi.tree(depotId),
    enabled,
    staleTime: 60 * 1000,
  })
}

export function useStockLocation(id: string | undefined) {
  return useQuery({
    queryKey: stockKeys.location(id ?? ''),
    queryFn: () => stockLocationsApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

/**
 * Un emplacement créé, modifié ou supprimé change la liste **et l'arbre**.
 *
 * Les deux vues lisent la même hiérarchie par deux routes différentes ; n'en
 * invalider qu'une afficherait un parent qui n'existe plus dans l'autre.
 */
function useInvalidateLocations() {
  const queryClient = useQueryClient()

  return () => void queryClient.invalidateQueries({ queryKey: stockKeys.locations() })
}

export function useCreateStockLocation() {
  const invalidate = useInvalidateLocations()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: StockLocationPayload) => stockLocationsApi.create(payload),
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
    mutationFn: ({ id, ...payload }: StockLocationUpdatePayload & { id: string }) =>
      stockLocationsApi.update(id, payload),
    onSuccess: () => {
      invalidate()
      toast.success(t('toast.updated'))
    },
  })
}

/**
 * Suppression d'un emplacement.
 *
 * Quatre dépendances sont en `restrictOnDelete` : les emplacements enfants, les
 * soldes, les réservations et `packages.current_stock_location_id`. Le serveur
 * refuse en 409 avec une phrase qui dit laquelle — elle est affichée telle
 * quelle, parce qu'« impossible de supprimer » n'aiderait personne.
 */
export function useDeleteStockLocation() {
  const invalidate = useInvalidateLocations()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => stockLocationsApi.remove(id),
    onSuccess: () => {
      invalidate()
      toast.success(t('toast.deleted'))
    },
    onError: (error) => {
      toast.error(error instanceof ApiError ? error.message : t('errors.unexpected'))
    },
  })
}
