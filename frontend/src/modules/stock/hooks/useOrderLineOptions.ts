import { useQuery } from '@tanstack/react-query'

import { ordersApi } from '@/modules/orders/api/orders.api'
import { orderKeys } from '@/modules/orders/hooks/useOrders'
import type { AsyncOption } from '@/shared/components/form/AsyncSelect'

/**
 * Commandes d'un client, pour choisir la ligne à servir.
 *
 * Le client est obligatoire : `OrderLine.order.customerId` doit être égal à
 * `StockItem.customerId`, et le serveur refuse le contraire. Proposer les
 * commandes de tout le monde ne servirait qu'à produire l'erreur.
 */
export function useCustomerOrderOptions(customerId: string) {
  const query = useQuery({
    queryKey: orderKeys.list({ page: 1, perPage: 50, customerId, sort: 'order_date' }),
    queryFn: () => ordersApi.list({ page: 1, perPage: 50, customerId, sort: 'order_date' }),
    enabled: customerId !== '',
    staleTime: 30 * 1000,
  })

  return {
    isLoading: customerId !== '' && query.isPending,
    options: (query.data?.data ?? []).map(
      (order): AsyncOption => ({
        value: order.id,
        label: order.orderNumber,
        hint: order.customerReference ?? order.externalReference ?? undefined,
      }),
    ),
  }
}

/**
 * Lignes d'une commande.
 *
 * Il n'existe pas de route `GET /order-lines` : les lignes arrivent avec la
 * commande, dans `OrderDetailResource`. La fiche est donc chargée entière — un
 * appel, pas un par ligne.
 *
 * Le libellé porte la quantité commandée et ce qui en est déjà réservé :
 * `reservedQuantity` est calculée par le serveur à partir des réservations
 * existantes, et c'est l'information qui décide de la quantité à saisir.
 */
export function useOrderLineOptions(orderId: string) {
  const query = useQuery({
    queryKey: orderKeys.detail(orderId),
    queryFn: () => ordersApi.get(orderId),
    enabled: orderId !== '',
  })

  const lines = query.data?.lines ?? []

  return {
    isLoading: orderId !== '' && query.isPending,
    options: lines.map(
      (line): AsyncOption => ({
        value: line.id,
        label: line.articleCode ?? line.name,
        hint: `${String(line.quantity)} · ${String(line.reservedQuantity ?? 0)}`,
      }),
    ),
    byId: new Map(lines.map((line) => [line.id, line])),
  }
}
