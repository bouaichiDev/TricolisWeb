import { describe, expect, it } from 'vitest'

import { buildJourney, looseEvents, type CarriedService } from './journey'
import type { TrackingEventDefinition } from '../types/trackingDefinition'
import type { TrackingEvent } from '../types/trackingEvent'

const DELIVERY = '01JQZ00000000000000SERV1'
const LOADING = '01JQZ00000000000000SERV2'

/** La livraison et le chargement, tels que la commande les porte. */
const carried: CarriedService[] = [
  { id: 'os-livraison', serviceId: DELIVERY },
  { id: 'os-chargement', serviceId: LOADING },
]

const step = (code: string, serviceId: string | null, position = 10): TrackingEventDefinition =>
  ({
    id: `def-${code}`,
    organizationId: 'org',
    sourceType: 'order_service',
    statusCode: 'completed',
    code,
    title: code,
    description: null,
    icon: null,
    position,
    apiConfigurationId: null,
    isLive: false,
    serviceId,
    visibleToCustomer: false,
    showsProofOfDelivery: false,
    active: true,
    createdAt: '2026-09-01T10:00:00.000000Z',
    updatedAt: '2026-09-01T10:00:00.000000Z',
  }) as TrackingEventDefinition

const event = (eventType: string, orderServiceId: string | null): TrackingEvent =>
  ({
    id: `evt-${eventType}-${orderServiceId ?? 'nul'}`,
    orderId: 'order',
    orderServiceId,
    eventType,
    status: 'completed',
    description: null,
    occurredAt: '2026-09-02T08:00:00.000000Z',
  }) as TrackingEvent

/**
 * Le défaut constaté : une commande dont la livraison était encore en route
 * s'affichait « livrée ». C'est son chargement qui était terminé.
 */
describe('à quelle étape appartient un événement', () => {
  it('ne franchit pas l’étape de la livraison avec un événement du chargement', () => {
    const steps = buildJourney(
      [step('Livree', DELIVERY)],
      [event('Livree', 'os-chargement')],
      carried,
    )

    expect(steps).toHaveLength(1)
    expect(steps[0].occurredAt).toBeNull()
  })

  it('la franchit avec l’événement de la livraison', () => {
    const steps = buildJourney(
      [step('Livree', DELIVERY)],
      [event('Livree', 'os-livraison')],
      carried,
    )

    expect(steps[0].occurredAt).not.toBeNull()
  })

  /** Une étape générale accepte n'importe quelle prestation, comme avant. */
  it('laisse une étape générale accepter tout événement', () => {
    const steps = buildJourney([step('Planifiee', null)], [event('Planifiee', 'os-chargement')], carried)

    expect(steps[0].occurredAt).not.toBeNull()
  })

  /**
   * Rien ne permet d'attribuer un événement de commande à une prestation :
   * l'attribuer à tort serait pire que ne pas le compter.
   */
  it('refuse un événement sans prestation sur une étape qui en nomme une', () => {
    const steps = buildJourney([step('Livree', DELIVERY)], [event('Livree', null)], carried)

    expect(steps[0].occurredAt).toBeNull()
  })

  /**
   * L'événement écarté ne doit pas disparaître sans un mot : il est survenu,
   * sous une configuration où l'étape ne visait encore aucune prestation.
   */
  it('montre à part l’événement qu’aucune étape ne revendique', () => {
    const orphan = event('Livree', 'os-chargement')

    expect(looseEvents([step('Livree', DELIVERY)], [orphan], carried)).toEqual([orphan])
  })

  it('ne montre pas à part un événement bien attaché', () => {
    const own = event('Livree', 'os-livraison')

    expect(looseEvents([step('Livree', DELIVERY)], [own], carried)).toEqual([])
  })
})

describe('le parcours d’une commande', () => {
  it('écarte les étapes d’une prestation que la commande ne porte pas', () => {
    const steps = buildJourney([step('Livree', DELIVERY), step('Montee', 'autre')], [], carried)

    expect(steps.map((item) => item.definition.code)).toEqual(['Livree'])
  })

  it('garde les étapes générales', () => {
    const steps = buildJourney([step('Planifiee', null), step('Montee', 'autre')], [], carried)

    expect(steps.map((item) => item.definition.code)).toEqual(['Planifiee'])
  })

  /**
   * Sans prestation connue — l'appelant n'en fournit pas — rien n'est écarté :
   * mieux vaut un parcours trop large qu'un parcours vide.
   */
  it('n’écarte rien quand les prestations sont inconnues', () => {
    const steps = buildJourney([step('Livree', DELIVERY), step('Montee', 'autre')], [])

    expect(steps).toHaveLength(2)
  })

  it('ordonne les étapes par leur rang', () => {
    const steps = buildJourney(
      [step('Livree', DELIVERY, 20), step('Planifiee', null, 10)],
      [event('Planifiee', 'os-livraison')],
      carried,
    )

    expect(steps.map((item) => [item.definition.code, item.occurredAt !== null])).toEqual([
      ['Planifiee', true],
      ['Livree', false],
    ])
  })
})
