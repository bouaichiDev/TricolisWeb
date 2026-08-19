import { api } from '@/shared/api/client'
import type { ApiResource } from '@/shared/api/types'

import type { OrderLine, OrderPackage, OrderService } from '../types/orderDetail'

/** Charge utile relevée sur `StoreOrderLineRequest` / `UpdateOrderLineRequest`. */
export interface OrderLineInput {
  catalogItemId?: string | null
  name?: string | null
  articleCode?: string | null
  barcode?: string | null
  externalReference?: string | null
  description?: string | null
  quantity?: number
  weight?: number
  volume?: number
}

/** Charge utile relevée sur `StorePackageRequest` / `UpdatePackageRequest`. */
export interface OrderPackageInput {
  parentPackageId?: string | null
  packageTypeId?: string | null
  groupingTypeId?: string | null
  barcode?: string | null
  reference?: string | null
  description?: string | null
  quantity?: number
  weight?: number
  volume?: number
}

/**
 * Charge utile d'un service de commande.
 *
 * `status` n'y figure pas à la modification : `UpdateOrderServiceRequest` ne
 * l'accepte pas, il passe par sa propre route et sa propre permission.
 */
export interface OrderServiceInput {
  serviceId?: string
  addressId?: string
  serviceNumber?: string
  sequence?: number
  requestedDate?: string
  requestedFrom?: string | null
  requestedTo?: string | null
  quantity?: number
  unit?: string
  requiredTimeMinutes?: number
  remainingTimeMinutes?: number
  weight?: number
  volume?: number
  packageCount?: number
  customerUnitPrice?: number
  customerTotalPrice?: number
  providerUnitCost?: number
  providerTotalCost?: number
  instructions?: string | null
  status?: string
}

/**
 * Contenu d'une commande, pièce par pièce.
 *
 * Ces routes servent **après** la création : le formulaire initial envoie tout
 * d'un bloc, mais corriger une ligne d'une commande existante ne doit pas
 * réécrire la commande entière. Le backend les refuse dès que la commande
 * n'accepte plus de changement de contenu — c'est lui qui tranche, l'écran ne
 * fait que ne pas proposer l'impossible.
 */
export const orderContentApi = {
  createLine: (orderId: string, payload: OrderLineInput) =>
    api
      .post<ApiResource<OrderLine>>(`/orders/${orderId}/lines`, payload)
      .then((response) => response.data),

  updateLine: (orderId: string, lineId: string, payload: OrderLineInput) =>
    api
      .patch<ApiResource<OrderLine>>(`/orders/${orderId}/lines/${lineId}`, payload)
      .then((response) => response.data),

  deleteLine: (orderId: string, lineId: string) =>
    api.delete<void>(`/orders/${orderId}/lines/${lineId}`),

  createPackage: (orderId: string, payload: OrderPackageInput) =>
    api
      .post<ApiResource<OrderPackage>>(`/orders/${orderId}/packages`, payload)
      .then((response) => response.data),

  updatePackage: (orderId: string, packageId: string, payload: OrderPackageInput) =>
    api
      .patch<ApiResource<OrderPackage>>(`/orders/${orderId}/packages/${packageId}`, payload)
      .then((response) => response.data),

  deletePackage: (orderId: string, packageId: string) =>
    api.delete<void>(`/orders/${orderId}/packages/${packageId}`),

  createService: (orderId: string, payload: OrderServiceInput) =>
    api
      .post<ApiResource<OrderService>>(`/orders/${orderId}/services`, payload)
      .then((response) => response.data),

  updateService: (orderId: string, serviceId: string, payload: OrderServiceInput) =>
    api
      .patch<ApiResource<OrderService>>(`/orders/${orderId}/services/${serviceId}`, payload)
      .then((response) => response.data),

  deleteService: (orderId: string, serviceId: string) =>
    api.delete<void>(`/orders/${orderId}/services/${serviceId}`),
}
