import type { Tour, TourStop } from '@/modules/tours/types/tour'

import type { PoolOrder } from './types/pool'

/** Un point plaçable : latitude et longitude y sont acquises, plus nullables. */
export interface PlanningPoint {
  key: string
  latitude: number
  longitude: number
  label: string
  /** Commandes distinctes attendant à cette adresse, identifiant compris. */
  orders: { id: string; orderNumber: string }[]
  serviceIds: string[]
}

/**
 * Les commandes du pool, groupées **par adresse**.
 *
 * Une commande peut avoir des services à plusieurs adresses, et plusieurs
 * commandes se donner rendez-vous à la même : le §69 interdit de forcer un
 * marqueur par commande. Ce qu'on pose sur la carte est un lieu, et ce qui y
 * attend.
 */
export function poolPoints(orders: PoolOrder[]): PlanningPoint[] {
  const byAddress = new Map<string, PlanningPoint>()

  for (const order of orders) {
    for (const service of order.services) {
      if (service.latitude === null || service.longitude === null) continue

      const key = service.addressId ?? `${service.latitude},${service.longitude}`
      const existing = byAddress.get(key)

      if (existing === undefined) {
        byAddress.set(key, {
          key,
          latitude: service.latitude,
          longitude: service.longitude,
          label: service.addressLabel ?? order.orderNumber,
          orders: [{ id: order.id, orderNumber: order.orderNumber }],
          serviceIds: [service.id],
        })
        continue
      }

      existing.serviceIds.push(service.id)

      if (!existing.orders.some((known) => known.id === order.id)) {
        existing.orders.push({ id: order.id, orderNumber: order.orderNumber })
      }
    }
  }

  return [...byAddress.values()]
}

/**
 * Ce que la carte ne peut pas montrer.
 *
 * Une adresse sans coordonnées reste planifiable — le §74 l'exige — mais elle
 * disparaîtrait de la carte sans un mot, et le planificateur croirait la
 * commande absente plutôt que non géocodée.
 */
export function unplottableCount(orders: PoolOrder[]): number {
  return orders.reduce(
    (total, order) =>
      total +
      order.services.filter((s) => s.latitude === null || s.longitude === null).length,
    0,
  )
}

export interface PlottedStop extends TourStop {
  latitude: number
  longitude: number
}

/** Les arrêts placés, dans l'ordre de la tournée. */
export function stopPoints(tour: Tour): PlottedStop[] {
  return (tour.stops ?? [])
    .filter((stop): stop is PlottedStop => {
      return (
        stop.latitude !== null &&
        stop.latitude !== undefined &&
        stop.longitude !== null &&
        stop.longitude !== undefined
      )
    })
    .sort((a, b) => a.sequence - b.sequence)
}

/**
 * Cet arrêt est-il le départ au dépôt ?
 *
 * La planification promeut le chargement au dépôt en première position : quand
 * la tournée a un dépôt, l'arrêt 1 **est** ce départ. C'est l'invariant que
 * tient `PlanOrderServices`, et le §68 demande de ne pas le confondre avec un
 * client.
 */
export function isDeparture(tour: Tour, stop: TourStop): boolean {
  return tour.depotId !== null && stop.sequence === 1
}
