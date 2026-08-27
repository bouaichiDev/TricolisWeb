import { describe, expect, it } from 'vitest'

import { deliveryPoint } from './focus'
import type { PoolOrder, PoolService } from './types/pool'

const service = (overrides: Partial<PoolService>): PoolService => ({
  id: 'svc',
  serviceNumber: 'S',
  serviceCode: null,
  serviceName: null,
  isLoading: false,
  status: 'ready_to_plan',
  addressId: 'addr',
  addressLabel: null,
  latitude: 1,
  longitude: 1,
  requestedDate: null,
  requestedFrom: null,
  requestedTo: null,
  weight: 0,
  volume: 0,
  packageCount: 0,
  ...overrides,
})

const order = (services: PoolService[]): PoolOrder => ({
  id: 'o',
  orderNumber: 'CMD',
  customerId: 'c',
  status: 'ready',
  earliestRequestedDate: null,
  serviceCount: services.length,
  addressCount: 1,
  totalWeight: 0,
  totalVolume: 0,
  totalPackages: 0,
  services,
})

describe('point visé par une commande', () => {
  /**
   * Le chargement se fait au dépôt : toutes les commandes y pointent le même
   * lieu, et y aller n'apprend rien sur celle qu'on regarde.
   */
  it('vise la livraison plutôt que le chargement', () => {
    const point = deliveryPoint(
      order([
        service({ id: 'load', isLoading: true, latitude: 46.23, longitude: 6.08 }),
        service({ id: 'deliver', latitude: 47.37, longitude: 8.54 }),
      ]),
    )

    expect(point).toEqual({ latitude: 47.37, longitude: 8.54 })
  })

  /** Une commande qui n'a qu'un chargement se joue au dépôt : on y va. */
  it('retombe sur le chargement quand il est seul', () => {
    const point = deliveryPoint(
      order([service({ id: 'load', isLoading: true, latitude: 46.23, longitude: 6.08 })]),
    )

    expect(point).toEqual({ latitude: 46.23, longitude: 6.08 })
  })

  /** Une adresse non géocodée ne se vise pas : elle n'a pas de point. */
  it('ignore les services sans coordonnées', () => {
    const point = deliveryPoint(
      order([
        service({ id: 'deliver', latitude: null, longitude: null }),
        service({ id: 'load', isLoading: true, latitude: 46.23, longitude: 6.08 }),
      ]),
    )

    expect(point).toEqual({ latitude: 46.23, longitude: 6.08 })
  })

  it('rend null quand rien n’est plaçable', () => {
    expect(deliveryPoint(order([service({ latitude: null, longitude: null })]))).toBeNull()
  })
})
