/** Tournée — `TourListResource` / `TourDetailResource`. */
export interface Tour {
  id: string
  organizationId: string
  tourNumber: string
  tourDate: string | null
  agencyId: string
  depotId: string | null
  providerId: string | null
  vehicleId: string | null
  driverId: string | null
  tourType: string | null
  instructions: string | null
  totalWeight: number | string
  totalVolume: number | string
  totalPackages: number
  totalCustomers: number
  drivingTimeMinutes: number
  workingTimeMinutes: number
  distanceMeters: number
  status: string
  stopCount?: number
  /** Rendu seulement sur `?withStops=1` : la vue en colonnes les montre. */
  stops?: TourStop[]
}

/** Arrêt d'une tournée — `TourStopResource`. */
export interface TourStop {
  id: string
  tourId: string
  addressId: string
  sequence: number
  status: string
  addressLabel?: string | null
  serviceCount?: number
}

export interface TourFilters {
  page: number
  perPage: number
  search?: string
  status?: string
  agencyId?: string
  depotId?: string
  providerId?: string
  driverId?: string
  vehicleId?: string
  tourDate?: string
  tourDateFrom?: string
  tourDateTo?: string
  /** Charge les arrêts sous chaque tournée, pour la vue en colonnes. */
  withStops?: boolean
  sort?: string
  direction?: 'asc' | 'desc'
}
