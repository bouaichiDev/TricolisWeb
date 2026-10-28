/** Les actions qu'offre le serveur sur une ressource : l'interface les suit. */
export interface ResourceActions {
  canUpdate: boolean
  /** Chauffeurs : le compte est propre au chauffeur, son identité se modifie ici. */
  canEditAccount?: boolean
  canDeactivate: boolean
  canActivate: boolean
}

export interface Driver {
  id: string
  code: string
  name: string
  firstName: string | null
  lastName: string | null
  email: string | null
  phone: string | null
  status: string
  statusLabel: string | null
  actions: ResourceActions
}

export interface DriverPayload {
  code: string
  firstName: string
  lastName: string
  email?: string
  phone: string | null
}

export interface ResourceFilters {
  search: string
  status: string
  page: number
  perPage?: number
}
