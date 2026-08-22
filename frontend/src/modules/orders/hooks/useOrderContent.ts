import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import {
  orderContentApi,
  type OrderLineInput,
  type OrderPackageInput,
  type OrderServiceInput,
} from '../api/orderContent.api'
import { ordersApi } from '../api/orders.api'
import { orderKeys } from './useOrders'

/**
 * Modifications du contenu d'une commande existante.
 *
 * Toute écriture invalide la fiche : lignes, colis et services y sont
 * entremêlés — retirer une ligne change les affectations des colis, ajouter un
 * colis change `packageCount`, et le serveur recalcule poids et volume. Ne
 * rafraîchir que la collection touchée laisserait le reste faux à l'écran.
 */
/**
 * @param message Libellé du toast, ou `null` pour ne rien annoncer.
 *
 * `null` sert aux écritures que **l'utilisateur n'a pas demandées** : annoncer
 * « Création effectuée » à l'ouverture d'un panneau fait porter à un geste de
 * lecture le vocabulaire d'un geste d'écriture, et la notification revient à
 * chaque ouverture. Le changement se voit dans les chiffres de la ligne.
 */
function useContentMutation<TVariables>(
  orderId: string,
  mutationFn: (variables: TVariables) => Promise<unknown>,
  message: 'created' | 'updated' | 'deleted' | null,
) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: orderKeys.detail(orderId) })
      void queryClient.invalidateQueries({ queryKey: orderKeys.packageTree(orderId) })
      void queryClient.invalidateQueries({ queryKey: orderKeys.lists() })
      if (message !== null) toast.success(t(`toast.${message}`))
    },
  })
}

export function useCreateOrderLine(orderId: string) {
  return useContentMutation(
    orderId,
    (payload: OrderLineInput) => orderContentApi.createLine(orderId, payload),
    'created',
  )
}

export function useUpdateOrderLine(orderId: string) {
  return useContentMutation(
    orderId,
    ({ id, ...payload }: OrderLineInput & { id: string }) =>
      orderContentApi.updateLine(orderId, id, payload),
    'updated',
  )
}

export function useDeleteOrderLine(orderId: string) {
  return useContentMutation(
    orderId,
    (lineId: string) => orderContentApi.deleteLine(orderId, lineId),
    'deleted',
  )
}

export function useCreateOrderPackage(orderId: string) {
  return useContentMutation(
    orderId,
    (payload: OrderPackageInput) => orderContentApi.createPackage(orderId, payload),
    'created',
  )
}

export function useUpdateOrderPackage(orderId: string) {
  return useContentMutation(
    orderId,
    ({ id, ...payload }: OrderPackageInput & { id: string }) =>
      orderContentApi.updatePackage(orderId, id, payload),
    'updated',
  )
}

export function useDeleteOrderPackage(orderId: string) {
  return useContentMutation(
    orderId,
    (packageId: string) => orderContentApi.deletePackage(orderId, packageId),
    'deleted',
  )
}

export function useCreateOrderService(orderId: string) {
  return useContentMutation(
    orderId,
    (payload: OrderServiceInput) => orderContentApi.createService(orderId, payload),
    'created',
  )
}

export function useUpdateOrderService(orderId: string) {
  return useContentMutation(
    orderId,
    ({ id, ...payload }: OrderServiceInput & { id: string }) =>
      orderContentApi.updateService(orderId, id, payload),
    'updated',
  )
}

export function useDeleteOrderService(orderId: string) {
  return useContentMutation(
    orderId,
    (serviceId: string) => orderContentApi.deleteService(orderId, serviceId),
    'deleted',
  )
}

/**
 * Statut d'un service.
 *
 * Route et permission distinctes de la modification — `order_services.change_status`
 * — parce qu'avancer une prestation dans son cycle n'est pas la même
 * responsabilité que corriger son adresse.
 */
export function useChangeOrderServiceStatus(orderId: string) {
  return useContentMutation(
    orderId,
    ({ serviceId, status }: { serviceId: string; status: string }) =>
      ordersApi.changeServiceStatus(orderId, serviceId, status),
    'updated',
  )
}

/** Affectation d'une ligne à un colis, sur une commande déjà créée. */
/**
 * @param silent Vrai pour le rattachement automatique, qui ne s'annonce pas.
 */
export function useAssignPackageLine(orderId: string, silent = false) {
  return useContentMutation(
    orderId,
    ({
      packageId,
      orderLineId,
      quantity,
    }: {
      packageId: string
      orderLineId: string
      quantity: number
    }) => ordersApi.assignPackageLine(orderId, packageId, { orderLineId, quantity }),
    silent ? null : 'created',
  )
}

export function useUpdatePackageLine(orderId: string) {
  return useContentMutation(
    orderId,
    ({
      packageId,
      orderLineId,
      quantity,
    }: {
      packageId: string
      orderLineId: string
      quantity: number
    }) => ordersApi.updatePackageLine(orderId, packageId, orderLineId, { orderLineId, quantity }),
    'updated',
  )
}

export function useDetachPackageLine(orderId: string) {
  return useContentMutation(
    orderId,
    ({ packageId, lineId }: { packageId: string; lineId: string }) =>
      ordersApi.detachPackageLine(orderId, packageId, lineId),
    'deleted',
  )
}
