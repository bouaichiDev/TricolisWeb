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
  /** Rendus par la liste : la colonne montre qui conduit, avec quoi. */
  driverName?: string | null
  vehicleRegistration?: string | null
  /** Commandes distinctes de la tournée ; rendu seulement avec les arrêts. */
  orderCount?: number
  /**
   * Qui réserve ce brouillon, nommé.
   *
   * Nul hors brouillon : l'exclusivité cesse dès que la tournée est validée ou
   * annulée, et afficher un nom laisserait croire le contraire.
   */
  plannedBy?: { id: string; name: string } | null
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
  /** Commandes posées sur l'arrêt, sans doublon : de quoi remonter à chacune. */
  orders?: StopOrder[]
  /** Temps total sur place, somme des services actifs de l'arrêt. */
  totalServiceMinutes?: number
}

/**
 * Creation / modification d'une tournee — `StoreTourRequest`.
 *
 * Le statut n'est pas saisi : une tournee nait au brouillon et change d'etat
 * par les passages du referentiel, depuis sa fiche. Le numero non plus : le
 * serveur l'attribue, un entier qui avance de un.
 */
export interface TourPayload {
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

/** Une commande vue depuis l'arrêt qui la dessert. */
export interface StopOrder {
  id: string
  orderNumber: string | null
  customerReference: string | null
  customerId: string
  customerName: string | null
  weight: number
  volume: number
  packageCount: number
  /** Temps que le camion passe ici pour cette commande. */
  serviceMinutes: number
  services: {
    id: string
    serviceNumber: string
    name: string | null
    code: string | null
    minutes: number
    status: string
  }[]
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
