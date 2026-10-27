import type { CalendarOrderEvent } from '@/modules/calendar/types'
import type { PublicTracking } from '@/modules/public-tracking/types'
import type { OrderTracking, TrackedService } from '@/modules/tracking/types'

const planned = { code: 'planned', label: 'Planifiée', icon: null }
const unavailableEta = { available: false, estimatedAt: null, source: null }

export function makeService(overrides: Partial<TrackedService> = {}): TrackedService {
  return {
    id: '01SVC1',
    serviceNumber: 'S1',
    sequence: 1,
    service: { code: 'DELIVERY', name: 'Livraison' },
    status: { code: 'planned', label: 'Planifié', icon: null },
    completed: false,
    address: { name: 'Entrepôt', addressLine1: 'Rue du Lac 1', postalCode: '1000', city: 'Lausanne', country: 'CH', latitude: 46.5, longitude: 6.6 },
    requested: { date: '2026-10-01', from: '08:00', to: '12:00' },
    slot: { start: '2026-10-01T09:30:00', end: '2026-10-01T10:00:00', allDay: false, source: 'planned' },
    stop: null,
    eta: unavailableEta,
    progress: null,
    currentPosition: null,
    proofOfDelivery: null,
    ...overrides,
  }
}

export function makeTracking(overrides: Partial<OrderTracking> = {}): OrderTracking {
  return {
    order: { id: '01ORDER', reference: 'IKEA-2026-000001', customerReference: 'IKEA-4587', orderDate: '2026-09-28T10:00:00+02:00', status: planned },
    available: true,
    isActive: true,
    refreshSeconds: 45,
    progress: { percent: 50, completedServices: 1, totalServices: 2 },
    services: [makeService(), makeService({ id: '01SVC2', serviceNumber: 'S2', service: { code: 'ASSEMBLY', name: 'Montage' } })],
    timeline: [
      { type: 'created', title: 'Commande créée', description: null, at: '2026-09-28T10:00:00+02:00', state: 'done', orderServiceId: null },
      { type: 'journey', title: 'Livraison en route', description: null, at: '2026-10-01T08:00:00+02:00', state: 'done', orderServiceId: '01SVC1' },
      { type: 'planned', title: 'Montage : passage prévu', description: null, at: '2026-10-01T14:00:00+02:00', state: 'expected', orderServiceId: '01SVC2' },
    ],
    map: { available: false, currentPosition: null, points: [] },
    eta: { ...unavailableEta, orderServiceId: null },
    ...overrides,
  }
}

export function makeCalendarEvent(overrides: Partial<CalendarOrderEvent['extendedProps']['actions']> = {}): CalendarOrderEvent {
  return {
    id: '01ORDER',
    title: 'IKEA-2026-000001',
    start: '2026-09-15T09:00:00',
    end: '2026-09-15T11:00:00',
    allDay: false,
    extendedProps: {
      reference: 'IKEA-2026-000001',
      customerReference: 'IKEA-4587',
      status: planned,
      serviceCount: 1,
      slotSource: 'planned',
      services: [{ id: '01SVC1', name: 'Livraison', status: planned, start: '2026-09-15T09:00:00', end: '2026-09-15T11:00:00', allDay: false, source: 'planned' }],
      actions: { canView: true, canEdit: false, canDuplicate: false, canCancel: false, canTrack: true, ...overrides },
    },
  }
}

export function makePublicTracking(overrides: Partial<PublicTracking> = {}): PublicTracking {
  return {
    organization: { name: 'Tricolis Dev', hasLogo: false },
    visibility: { showReference: true, showStatus: true, showDeliveryWindow: true, showTimeline: true, showEta: true, showMap: false },
    reference: { reference: 'IKEA-2026-000001', customerReference: 'IKEA-4587' },
    status: planned,
    progress: { percent: 0, completedServices: 0, totalServices: 1 },
    isActive: true,
    refreshSeconds: 45,
    services: [{ name: 'Livraison', status: 'Planifié', completed: false, slot: { start: '2026-10-01', end: null, allDay: true, source: 'requested' }, postalCode: '1000', city: 'Lausanne' }],
    timeline: [{ type: 'created', title: 'Commande créée', description: null, at: '2026-09-28T10:00:00+02:00', state: 'done' }],
    eta: { available: true, estimatedAt: '2026-10-01T09:30:00', source: 'planning' },
    map: null,
    ...overrides,
  }
}
