import { describe, expect, it } from 'vitest'

import type { Tour } from '@/modules/tours/types/tour'

import { isDeparture, poolPoints, stopPoints, unplottableCount } from './points'
import type { PoolOrder, PoolService } from './types/pool'

const service = (overrides: Partial<PoolService> = {}): PoolService => ({
  id: 'svc-1',
  serviceNumber: 'S-1',
  serviceCode: 'DEL',
  serviceName: 'Livraison',
  status: 'pending',
  addressId: 'addr-1',
  addressLabel: 'Rabat',
  latitude: 34,
  longitude: -6,
  requestedDate: '2026-09-01',
  requestedFrom: null,
  requestedTo: null,
  weight: 10,
  volume: 1,
  packageCount: 1,
  ...overrides,
})

const order = (id: string, services: PoolService[]): PoolOrder => ({
  id,
  orderNumber: `CMD-${id}`,
  customerId: 'cus-1',
  status: 'ready',
  earliestRequestedDate: '2026-09-01',
  serviceCount: services.length,
  addressCount: new Set(services.map((s) => s.addressId)).size,
  totalWeight: 0,
  totalVolume: 0,
  totalPackages: 0,
  services,
})

describe('projection des points de la carte', () => {
  /** §69 : une commande peut avoir plusieurs adresses, une adresse plusieurs commandes. */
  it('groupe par adresse, pas par commande', () => {
    const points = poolPoints([
      order('a', [service({ id: 's1' }), service({ id: 's2', addressId: 'addr-2', latitude: 35 })]),
      order('b', [service({ id: 's3' })]),
    ])

    expect(points).toHaveLength(2)

    const shared = points.find((point) => point.key === 'addr-1')

    expect(shared?.serviceIds).toEqual(['s1', 's3'])
    expect(shared?.orders.map((o) => o.orderNumber)).toEqual(['CMD-a', 'CMD-b'])
  })

  /**
   * §74 : une adresse non géocodée reste planifiable. Elle ne doit pas
   * apparaître sur la carte, mais son absence doit se compter — sinon on croit
   * la commande disparue plutôt que non géocodée.
   */
  it('écarte les adresses sans coordonnées et les compte', () => {
    const orders = [
      order('a', [service({ id: 's1', latitude: null, longitude: null }), service({ id: 's2' })]),
    ]

    expect(poolPoints(orders)).toHaveLength(1)
    expect(unplottableCount(orders)).toBe(1)
  })

  it('ordonne les arrêts par séquence et ignore ceux sans point', () => {
    const tour = {
      id: 't1',
      depotId: 'dep-1',
      stops: [
        { id: 'b', tourId: 't1', addressId: 'a2', sequence: 2, status: 'pending', latitude: 34, longitude: -6 },
        { id: 'c', tourId: 't1', addressId: 'a3', sequence: 3, status: 'pending', latitude: null, longitude: null },
        { id: 'a', tourId: 't1', addressId: 'a1', sequence: 1, status: 'pending', latitude: 33, longitude: -7 },
      ],
    } as unknown as Tour

    expect(stopPoints(tour).map((stop) => stop.id)).toEqual(['a', 'b'])
  })

  /** §68 : le dépôt ne doit pas se confondre avec un client. */
  it('reconnaît le départ au dépôt, et seulement quand il y en a un', () => {
    const first = { id: 'a', tourId: 't', addressId: 'x', sequence: 1, status: 'pending' }
    const second = { id: 'b', tourId: 't', addressId: 'y', sequence: 2, status: 'pending' }

    expect(isDeparture({ depotId: 'dep-1' } as Tour, first)).toBe(true)
    expect(isDeparture({ depotId: 'dep-1' } as Tour, second)).toBe(false)
    expect(isDeparture({ depotId: null } as Tour, first)).toBe(false)
  })
})
