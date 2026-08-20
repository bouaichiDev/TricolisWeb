import { api } from '@/shared/api/client'
import type { ApiResource } from '@/shared/api/types'

import type { OrderPackage } from '../types/orderDetail'

/** Prise en charge d'un colis par un service — `OrderServicePackageResource`. */
export interface ServicePackageLink {
  id: string
  orderServiceId: string
  packageId: string
  quantity: number | string | null
  handlingInstructions: string | null
  status: string | null
  package?: OrderPackage
}

export interface ServicePackageInput {
  packageId?: string
  quantity?: number
  handlingInstructions?: string | null
  status?: string
}

/**
 * Colis pris en charge par un service.
 *
 * C'est la relation `OrderServicePackage` du diagramme. Un même colis peut être
 * servi par plusieurs prestations — chargé ici, livré là — et chaque liaison
 * porte sa propre quantité et ses consignes.
 */
export const servicePackagesApi = {
  list: (orderId: string, serviceId: string) =>
    api
      .get<ApiResource<ServicePackageLink[]>>(`/orders/${orderId}/services/${serviceId}/packages`)
      .then((response) => response.data),

  create: (orderId: string, serviceId: string, payload: ServicePackageInput) =>
    api
      .post<ApiResource<ServicePackageLink>>(
        `/orders/${orderId}/services/${serviceId}/packages`,
        payload,
      )
      .then((response) => response.data),

  update: (orderId: string, serviceId: string, linkId: string, payload: ServicePackageInput) =>
    api
      .patch<ApiResource<ServicePackageLink>>(
        `/orders/${orderId}/services/${serviceId}/packages/${linkId}`,
        payload,
      )
      .then((response) => response.data),

  remove: (orderId: string, serviceId: string, linkId: string) =>
    api.delete<void>(`/orders/${orderId}/services/${serviceId}/packages/${linkId}`),
}
