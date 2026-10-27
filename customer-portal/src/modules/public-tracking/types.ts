import type { StatusView } from '@/modules/orders/types'
import type { Eta, Position, TimelineItem } from '@/modules/tracking/types'
import type { Slot } from '@/shared/utils/slot'

export interface PublicVisibility {
  showReference: boolean
  showStatus: boolean
  showDeliveryWindow: boolean
  showTimeline: boolean
  showEta: boolean
  showMap: boolean
}

/**
 * La réponse du lien public : chaque bloc fermé par le transporteur vaut
 * `null`. Aucun identifiant interne n'y figure.
 */
export interface PublicTracking {
  organization: { name: string; hasLogo: boolean }
  visibility: PublicVisibility
  reference: { reference: string; customerReference: string | null } | null
  status: StatusView | null
  progress: { percent: number | null; completedServices: number; totalServices: number } | null
  isActive: boolean
  refreshSeconds: number | null
  services: { name: string; status: string | null; completed: boolean; slot: Slot | null; postalCode: string | null; city: string | null }[] | null
  timeline: TimelineItem[] | null
  eta: Eta | null
  map: { available: boolean; currentPosition: Position | null; points: { label: string; latitude: number; longitude: number; completed: boolean }[] } | null
}
