import type { OrderActions, StatusView } from '@/modules/orders/types'

/** D'où vient la date affichée : la planification, ou la demande du client. */
export type SlotSource = 'planned' | 'requested'

export interface CalendarServiceSlot {
  id: string
  name: string | null
  status: StatusView
  start: string
  end: string | null
  allDay: boolean
  source: SlotSource
}

/** Un événement tel que le backend le rend, déjà au format FullCalendar. */
export interface CalendarOrderEvent {
  id: string
  title: string
  start: string
  end: string | null
  allDay: boolean
  extendedProps: {
    reference: string
    customerReference: string | null
    status: StatusView
    serviceCount: number
    slotSource: SlotSource
    services: CalendarServiceSlot[]
    actions: OrderActions
  }
}

export interface CalendarFilterOptions {
  statuses: { code: string; label: string }[]
  services: { id: string; code: string; name: string }[]
}

export interface CalendarMeta {
  from: string
  to: string
  count: number
  truncated: boolean
  filters: CalendarFilterOptions
}

export interface CalendarFilters {
  status?: string
  service?: string
  search?: string
}

/** La période visible, bornes au format `AAAA-MM-JJ`, fin exclue. */
export interface CalendarRange {
  from: string
  to: string
}
