import type { StatusView } from '@/modules/orders/types'
import type { Slot } from '@/shared/utils/slot'

export interface Eta {
  available: boolean
  estimatedAt: string | null
  source: string | null
}

export interface Position {
  latitude: number
  longitude: number
  recordedAt: string | null
}

export interface TimelineItem {
  type: 'created' | 'status' | 'journey' | 'planned' | 'arrived' | 'departed' | 'proof' | string
  title: string
  description: string | null
  at: string
  /** `done` : survenu ; `expected` : prévu, pas encore un fait. */
  state: 'done' | 'expected'
  orderServiceId?: string | null
}

export interface MapPoint {
  orderServiceId?: string
  label: string
  latitude: number
  longitude: number
  completed: boolean
}

export interface TrackedService {
  id: string
  serviceNumber: string
  sequence: number
  service: { code: string; name: string } | null
  status: StatusView
  completed: boolean
  address: {
    name: string | null
    addressLine1: string | null
    postalCode: string | null
    city: string | null
    country: string | null
    latitude: number | null
    longitude: number | null
  } | null
  requested: { date: string | null; from: string | null; to: string | null }
  slot: Slot | null
  stop: {
    status: StatusView
    plannedArrivalAt: string | null
    plannedDepartureAt: string | null
    actualArrivalAt: string | null
    actualDepartureAt: string | null
  } | null
  eta: Eta
  /** Progression dans le parcours configuré ; `null` sans parcours. */
  progress: { reached: number; total: number; percent: number } | null
  currentPosition: Position | null
  proofOfDelivery: { deliveredAt: string | null; recipientName: string | null } | null
}

export interface OrderTracking {
  order: { id: string; reference: string; customerReference: string | null; orderDate: string | null; status: StatusView }
  available: boolean
  isActive: boolean
  /** Intervalle de rafraîchissement proposé, `null` quand la commande n'évolue plus. */
  refreshSeconds: number | null
  progress: { percent: number | null; completedServices: number; totalServices: number }
  services: TrackedService[]
  timeline: TimelineItem[]
  map: { available: boolean; currentPosition: (Position & { orderServiceId?: string }) | null; points: MapPoint[] }
  eta: Eta & { orderServiceId: string | null }
}

export interface PublicLinkState {
  exists: boolean
  state: 'none' | 'active' | 'revoked' | 'expired'
  active: boolean
  expiresAt?: string | null
  createdAt?: string | null
  revokedAt?: string | null
  lastAccessAt?: string | null
  accessCount?: number
  /** Présente seulement dans la réponse qui crée le lien. */
  publicUrl: string | null
}
