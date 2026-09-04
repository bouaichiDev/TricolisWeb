import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { toursApi } from '../api/tours.api'
import type { TourFilters, TourPayload } from '../types/tour'

export const tourKeys = {
  all: ['tours'] as const,
  list: (filters: TourFilters) => [...tourKeys.all, 'list', filters] as const,
  detail: (id: string) => [...tourKeys.all, 'detail', id] as const,
  route: (id: string) => [...tourKeys.all, 'route', id] as const,
}

/**
 * Le tracé routier d'une tournée.
 *
 * Le serveur le recalcule et le garde en cache une heure sous une clé qui
 * dépend des points : réordonner les arrêts donne un autre tracé sans qu'on
 * ait à l'invalider. Côté écran, une minute suffit à éviter de le redemander à
 * chaque ouverture de la carte.
 */
export function useTourRoute(id: string | null) {
  return useQuery({
    queryKey: tourKeys.route(id ?? ''),
    queryFn: () => toursApi.routeGeometry(id as string),
    enabled: id !== null && id !== '',
    staleTime: 60 * 1000,
  })
}

export function useTourList(filters: TourFilters) {
  return useQuery({
    queryKey: tourKeys.list(filters),
    queryFn: () => toursApi.list(filters),
    placeholderData: (previous) => previous,
  })
}

export function useTour(id: string | null) {
  return useQuery({
    queryKey: tourKeys.detail(id ?? ''),
    queryFn: () => toursApi.get(id as string),
    enabled: id !== null && id !== '',
  })
}

export function useCreateTour() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    // Une tournée naît toujours au brouillon : c'est le seul état depuis lequel
    // on peut encore y verser des commandes.
    mutationFn: (payload: TourPayload) => toursApi.create({ ...payload, status: 'draft' }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: tourKeys.all })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateTour(id: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: TourPayload) => toursApi.update(id, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: tourKeys.all })
      toast.success(t('toast.updated'))
    },
  })
}

/** Réserver la tournée le temps de la composer sur la carte. */
export function useReserveTour() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => toursApi.reserve(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: tourKeys.all })
    },
  })
}

/** Rendre la tournée après l'avoir composée ; le statut ne bouge pas. */
export function useReleaseTour() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => toursApi.release(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: tourKeys.all })
    },
  })
}

export function useChangeTourStatus() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: ({ id, status }: { id: string; status: string }) =>
      toursApi.changeStatus(id, status),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: tourKeys.all })
      toast.success(t('toast.updated'))
    },
  })
}
