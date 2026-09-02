import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { servicesApi, type ServicePayload } from '../api/services.api'
import type { ServiceFilters } from '../types/service'

export const serviceKeys = {
  all: ['services'] as const,
  lists: () => [...serviceKeys.all, 'list'] as const,
  list: (filters: ServiceFilters) => [...serviceKeys.lists(), filters] as const,
  detail: (id: string) => [...serviceKeys.all, 'detail', id] as const,
}

export function useServiceList(filters: ServiceFilters, enabled = true) {
  return useQuery({
    queryKey: serviceKeys.list(filters),
    queryFn: () => servicesApi.list(filters),
    placeholderData: (previous) => previous,
    // Une fenetre fermee ne charge rien : `enabled` laisse l'appelant differer
    // la requete jusqu'a ce que son ecran soit reellement ouvert.
    enabled,
  })
}

export function useService(id: string | undefined) {
  return useQuery({
    queryKey: serviceKeys.detail(id ?? ''),
    queryFn: () => servicesApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

/**
 * Services actifs, pour les listes déroulantes du formulaire de commande.
 *
 * Cache long : le référentiel change rarement, et le recharger à chaque
 * ouverture d'un service de commande ferait clignoter le sélecteur.
 */
export function useActiveServices() {
  return useQuery({
    queryKey: [...serviceKeys.lists(), 'active'],
    queryFn: () => servicesApi.list({ page: 1, perPage: 100, status: 'active' }),
    staleTime: 10 * 60 * 1000,
  })
}

export function useCreateService() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: ServicePayload) => servicesApi.create(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: serviceKeys.lists() })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateService(id: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: Partial<ServicePayload>) => servicesApi.update(id, payload),
    onSuccess: (service) => {
      queryClient.setQueryData(serviceKeys.detail(id), service)
      void queryClient.invalidateQueries({ queryKey: serviceKeys.lists() })
      toast.success(t('toast.updated'))
    },
  })
}

export function useDeleteService() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => servicesApi.remove(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: serviceKeys.lists() })
      toast.success(t('toast.deleted'))
    },
  })
}
