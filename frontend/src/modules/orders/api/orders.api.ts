import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { AuditLog } from '@/modules/audit/types/auditLog'
import type { OrderDetail, PackageTreeNode } from '../types/orderDetail'
import type { OrderFilters, OrderListItem } from '../types/order'
import type {
  CreateOrderPayload,
  DuplicateOrderPayload,
  UpdateOrderPayload,
} from '../types/orderPayload'

export const ordersApi = {
  list: (filters: OrderFilters) =>
    api.get<ApiCollection<OrderListItem>>('/orders', { query: { ...filters } }),

  /**
   * Détail complet en un appel.
   *
   * `OrderDetailResource` renvoie lignes, colis et services — ces derniers avec
   * leur service, leur adresse, leurs contacts et leurs colis. Le §36 demande
   * de réutiliser ce contrat plutôt que de charger chaque onglet séparément.
   */
  get: (id: string) =>
    api.get<ApiResource<OrderDetail>>(`/orders/${id}`).then((response) => response.data),

  /** Création transactionnelle : commande, lignes, colis, services, d'un bloc. */
  create: (payload: CreateOrderPayload) =>
    api.post<ApiResource<OrderDetail>>('/orders', payload).then((response) => response.data),

  update: (id: string, payload: UpdateOrderPayload) =>
    api.patch<ApiResource<OrderDetail>>(`/orders/${id}`, payload).then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/orders/${id}`),

  /** Le statut visé doit figurer dans `allowedTransitions` de la commande. */
  changeStatus: (id: string, status: string) =>
    api
      .patch<ApiResource<OrderDetail>>(`/orders/${id}/status`, { status })
      .then((response) => response.data),

  duplicate: (id: string, payload: DuplicateOrderPayload) =>
    api
      .post<ApiResource<OrderDetail>>(`/orders/${id}/duplicate`, payload)
      .then((response) => response.data),

  /** Journal d'audit filtré sur la commande — pas un historique reconstruit. */
  history: (id: string, page: number) =>
    api.get<ApiCollection<AuditLog>>(`/orders/${id}/history`, { query: { page, perPage: 25 } }),

  packageTree: (id: string) =>
    api
      .get<ApiResource<PackageTreeNode[]>>(`/orders/${id}/packages/tree`)
      .then((response) => response.data),

  assignPackageLine: (
    orderId: string,
    packageId: string,
    payload: { orderLineId: string; quantity: number },
  ) => api.post<ApiResource<{ id: string }>>(`/orders/${orderId}/packages/${packageId}/lines`, payload),

  /**
   * Modifier une quantite affectee.
   *
   * `orderLineId` est exige en plus de l'identifiant dans l'URL :
   * `StorePackageLineRequest` sert aussi bien a la creation qu'a la mise a
   * jour, et le controleur relit la ligne depuis le corps.
   */
  updatePackageLine: (
    orderId: string,
    packageId: string,
    lineId: string,
    payload: { orderLineId: string; quantity: number },
  ) =>
    api.patch<ApiResource<{ id: string }>>(
      `/orders/${orderId}/packages/${packageId}/lines/${lineId}`,
      payload,
    ),

  detachPackageLine: (orderId: string, packageId: string, lineId: string) =>
    api.delete<void>(`/orders/${orderId}/packages/${packageId}/lines/${lineId}`),

  changeServiceStatus: (orderId: string, serviceId: string, status: string) =>
    api.patch<ApiResource<{ id: string }>>(`/orders/${orderId}/services/${serviceId}/status`, {
      status,
    }),
}
