import type { OrderDetail, OrderService } from '../types/orderDetail'

const num = (value: number | string | null | undefined): number => {
  if (value === null || value === undefined) return 0

  const parsed = Number(value)

  return Number.isFinite(parsed) ? parsed : 0
}

export interface OrderAmounts {
  customerUnitPrice: number
  customerTotalPrice: number
  providerUnitCost: number
  providerTotalCost: number
}

/**
 * Montants de la commande, **sommés depuis ses services**.
 *
 * `orders` ne porte aucun montant : ni total client, ni coût fournisseur. Le
 * diagramme les place sur `OrderService`, parce que c'est la prestation qui se
 * facture. La maquette les affiche pourtant au niveau de la commande — sur un
 * exemple à un seul service, la distinction ne se voyait pas.
 *
 * La somme est donc calculée ici, et assumée comme telle : c'est un agrégat
 * d'affichage, pas un champ. Inventer une colonne aurait été pire.
 */
export function orderAmounts(services: OrderService[]): OrderAmounts {
  return services.reduce<OrderAmounts>(
    (total, service) => ({
      customerUnitPrice: total.customerUnitPrice + num(service.billing.customerUnitPrice),
      customerTotalPrice: total.customerTotalPrice + num(service.billing.customerTotalPrice),
      providerUnitCost: total.providerUnitCost + num(service.providerCost.providerUnitCost),
      providerTotalCost: total.providerTotalCost + num(service.providerCost.providerTotalCost),
    }),
    { customerUnitPrice: 0, customerTotalPrice: 0, providerUnitCost: 0, providerTotalCost: 0 },
  )
}

export interface DeliveryWindow {
  from: string | null
  to: string | null
}

/**
 * Créneau de livraison de la commande.
 *
 * `orders.order_date` est la date **de la commande**, pas celle de la
 * livraison : celle-ci vit sur chaque service, dans `requestedDate` et les
 * bornes `requestedFrom` / `requestedTo`. Une commande à plusieurs services
 * couvre donc une plage, d'où le minimum et le maximum plutôt qu'une date.
 *
 * Sans service daté, il n'y a rien à afficher — et surtout pas la date de
 * commande à la place, qui répondrait à une autre question.
 */
export function deliveryWindow(services: OrderService[]): DeliveryWindow {
  const dates = services
    .flatMap((service) => [
      service.operational.requestedFrom,
      service.operational.requestedDate,
      service.operational.requestedTo,
    ])
    .filter((date): date is string => date !== null && date !== '')
    .sort()

  if (dates.length === 0) return { from: null, to: null }

  return { from: dates[0], to: dates[dates.length - 1] }
}

/** Compteurs affichés en bandeau, tous lus sur la ressource ou comptés. */
export function orderCounts(order: OrderDetail) {
  return {
    lines: order.lines?.length ?? 0,
    packages: order.packages?.length ?? 0,
    services: order.services?.length ?? 0,
  }
}
