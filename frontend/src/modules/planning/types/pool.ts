/** Une commande vue depuis la planification — `PlanningPoolResource`. */
export interface PoolService {
  id: string
  serviceNumber: string
  serviceCode: string | null
  serviceName: string | null
  status: string
  /**
   * Ce service est-il un chargement ?
   *
   * Le serveur tranche, d'après les codes réglés de l'organisation. L'écran s'en
   * sert pour centrer la carte sur la livraison : tous les chargements pointent
   * le même dépôt, et y aller n'apprend rien.
   */
  isLoading: boolean
  addressId: string | null
  addressLabel: string | null
  /** Nuls tant que l'adresse n'est pas géocodée : la carte ne peut alors la poser. */
  latitude: number | null
  longitude: number | null
  requestedDate: string | null
  requestedFrom: string | null
  requestedTo: string | null
  weight: number
  volume: number
  packageCount: number
}

/**
 * Seuls les services **encore à planifier** sont rendus, et les totaux ne
 * portent que sur eux : une commande à moitié planifiée n'apporte à la tournée
 * que ce qui reste.
 */
export interface PoolOrder {
  id: string
  orderNumber: string
  customerId: string
  customerName?: string
  status: string
  earliestRequestedDate: string | null
  serviceCount: number
  addressCount: number
  totalWeight: number
  totalVolume: number
  totalPackages: number
  services: PoolService[]
}

export interface PoolFilters {
  page: number
  perPage: number
  search?: string
  requestedDate?: string
  customerId?: string
  agencyId?: string
}

/** Motifs de refus rendus par la planification, en codes que l'écran traduit. */
export type PlanningRejection = {
  orderServiceId: string
  reason: 'already_assigned' | 'status' | 'no_address' | 'not_found' | 'not_planned'
}

export interface PlanningResult {
  planned: string[]
  rejected: PlanningRejection[]
}

/** Résultat d'un retrait — `POST /tours/{id}/unplan`. */
export interface UnplanningResult {
  unplanned: string[]
  rejected: PlanningRejection[]
}
