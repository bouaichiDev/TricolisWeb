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
  plannedStartAt: string | null
  plannedEndAt: string | null
  /** Rendus par `TourDetailResource` seulement : la liste n'en a pas l'usage. */
  actualStartAt?: string | null
  actualEndAt?: string | null
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
  /** Nuls tant que l'adresse n'est pas géocodée : l'arrêt existe sans être traçable. */
  latitude?: number | null
  longitude?: number | null
  /** Services **actifs** de l'arrêt : ce qu'on peut en retirer. */
  orderServiceIds?: string[]
}

/**
 * Creation / modification d'une tournee — `StoreTourRequest`.
 *
 * Le statut n'est pas saisi : une tournee nait au brouillon et change d'etat
 * par les passages du referentiel, depuis sa fiche.
 */
export interface TourPayload {
  tourNumber: string
  tourDate: string
  agencyId: string
  depotId: string | null
  providerId: string | null
  vehicleId: string | null
  driverId: string | null
  tourType: string | null
  instructions: string | null
  plannedStartAt: string | null
  plannedEndAt: string | null
  status?: string
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
