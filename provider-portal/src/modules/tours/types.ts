export interface StopService {
  id: string
  status: string
  serviceNumber: string | null
  serviceName: string | null
  requestedFrom: string | null
  requestedTo: string | null
  weight: string | null
  volume: string | null
  packageCount: number | null
  instructions: string | null
  contacts: { firstName: string | null; lastName: string | null; phone: string | null; mobile: string | null }[]
}

export interface TourStop {
  id: string
  sequence: number
  status: string
  statusLabel: string | null
  plannedArrivalAt: string | null
  plannedDepartureAt: string | null
  actualArrivalAt: string | null
  actualDepartureAt: string | null
  address: {
    name: string | null
    line1: string | null
    line2: string | null
    postalCode: string | null
    city: string | null
    country: string | null
    instructions: string | null
  } | null
  actions?: { canArrive: boolean; canComplete: boolean; canSkip: boolean }
  services: StopService[]
}

export interface Tour {
  id: string
  tourNumber: string
  tourDate: string
  status: string
  statusLabel: string
  agency?: string | null
  depot?: string | null
  driver?: { id: string; code: string; name: string } | null
  vehicle?: { id: string; code: string; registrationNumber: string } | null
  plannedStartAt: string | null
  plannedEndAt: string | null
  actualStartAt: string | null
  actualEndAt: string | null
  instructions: string | null
  totals: { weight: string; volume: string; packages: number; distanceMeters: number; drivingMinutes: number; workingMinutes: number }
  stops?: TourStop[]
  actions: { canAssign: boolean; canStart?: boolean; canComplete?: boolean }
}

export interface TourEvent {
  id: string
  title: string
  start: string
  end: string | null
  allDay: boolean
  status: string
}

export interface TourFilters {
  search: string
  status: string
  /** Onglet : `todo`, `running`, `done`, ou vide pour toutes. */
  state?: string
  page: number
  perPage?: number
}
