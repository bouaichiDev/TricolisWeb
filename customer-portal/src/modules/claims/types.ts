import type { StatusView } from '@/modules/orders/types'

export interface ClaimListItem {
  id: string
  title: string
  claimType: string
  status: StatusView
  isClosed: boolean
  order: { id: string; reference: string; customerReference: string | null } | null
  orderService: { id: string; serviceNumber: string; name: string | null } | null
  createdAt: string | null
  closedAt: string | null
}

/** Capacités calculées par le backend : le portail n'en déduit aucune. */
export interface ClaimActions {
  canEdit: boolean
  canAttach: boolean
}

export interface ClaimDetail extends ClaimListItem {
  description: string | null
  actions: ClaimActions
}

export interface ClaimFilters {
  search?: string
  claimType?: string
  state?: 'open' | 'closed' | ''
  orderId?: string
  orderServiceId?: string
  sort?: string
  direction?: 'asc' | 'desc'
  page?: number
  perPage?: number
}

/** Ce que le client décrit — le reste est imposé par le backend. */
export interface ClaimPayload {
  orderId?: string | null
  orderServiceId?: string | null
  title: string
  description?: string | null
  claimType: string
}
