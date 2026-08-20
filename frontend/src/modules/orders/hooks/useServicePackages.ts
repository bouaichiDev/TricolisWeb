import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { servicePackagesApi, type ServicePackageInput } from '../api/servicePackages.api'
import { orderKeys } from './useOrders'

export const servicePackageKeys = {
  all: ['service-packages'] as const,
  list: (orderId: string, serviceId: string) =>
    [...servicePackageKeys.all, orderId, serviceId] as const,
}

export function useServicePackages(orderId: string, serviceId: string, enabled = true) {
  return useQuery({
    queryKey: servicePackageKeys.list(orderId, serviceId),
    queryFn: () => servicePackagesApi.list(orderId, serviceId),
    enabled: enabled && orderId !== '' && serviceId !== '',
  })
}

/**
 * Écritures sur les colis pris en charge.
 *
 * La fiche de la commande est invalidée en plus de la liste : `OrderDetail`
 * porte les mêmes liaisons dans ses services, et ne rafraîchir que l'une
 * laisserait l'autre fausse à l'écran.
 */
function useLinkMutation<TVariables>(
  orderId: string,
  serviceId: string,
  mutationFn: (variables: TVariables) => Promise<unknown>,
  message: 'created' | 'updated' | 'deleted',
) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: servicePackageKeys.list(orderId, serviceId) })
      void queryClient.invalidateQueries({ queryKey: orderKeys.detail(orderId) })
      toast.success(t(`toast.${message}`))
    },
  })
}

export function useAttachServicePackage(orderId: string, serviceId: string) {
  return useLinkMutation(
    orderId,
    serviceId,
    (payload: ServicePackageInput) => servicePackagesApi.create(orderId, serviceId, payload),
    'created',
  )
}

export function useUpdateServicePackage(orderId: string, serviceId: string) {
  return useLinkMutation(
    orderId,
    serviceId,
    ({ id, ...payload }: ServicePackageInput & { id: string }) =>
      servicePackagesApi.update(orderId, serviceId, id, payload),
    'updated',
  )
}

export function useDetachServicePackage(orderId: string, serviceId: string) {
  return useLinkMutation(
    orderId,
    serviceId,
    (linkId: string) => servicePackagesApi.remove(orderId, serviceId, linkId),
    'deleted',
  )
}
