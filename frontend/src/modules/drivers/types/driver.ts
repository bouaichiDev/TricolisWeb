/**
 * Chauffeur — `DriverListResource` / `DriverDetailResource`.
 *
 * Le schéma réel porte un **nom unique**, pas un prénom et un nom séparés, et
 * ne rattache pas de compte utilisateur. Il rattache en revanche une adresse et
 * un contact, comme le fournisseur. Écart consigné dans
 * `docs/frontend/phase-4-analysis.md`.
 */
export interface Driver {
  id: string
  organizationId: string
  providerId: string | null
  userId: string | null
  addressId: string | null
  contactId: string | null
  code: string
  name: string
  status: string
  providerName?: string
  userEmail?: string
  /** Chargé par `GET /drivers/{id}` ; absent de la liste. */
  user?: { id: string; firstName: string; lastName: string; email: string } | null
  /**
   * Appartenance du compte à l'organisation.
   *
   * C'est elle qu'ouvre la fiche d'un membre — `/users/{membershipId}` — et non
   * l'identifiant de l'utilisateur, qui menait à une page introuvable.
   */
  membershipId?: string | null
}

/**
 * Création d'un chauffeur — `StoreDriverRequest`.
 *
 * L'identité sert au chauffeur **et** à son compte : le serveur crée les deux
 * ensemble, compose `name` du prénom et du nom, et rattache le rôle chauffeur.
 * Le fournisseur est facultatif : un transporteur emploie les siens.
 */
export interface DriverPayload {
  code: string
  status: string
  firstName: string
  lastName: string
  email: string
  phone?: string | null
  providerId?: string | null
  addressId?: string | null
  contactId?: string | null
}

/** Modification — l'identité du compte se change depuis la fiche utilisateur. */
export interface DriverUpdatePayload {
  code?: string
  name?: string
  status?: string
  providerId?: string | null
  addressId?: string | null
  contactId?: string | null
}

export interface DriverFilters {
  page: number
  perPage: number
  search?: string
  status?: string
  providerId?: string
  sort?: string
  direction?: 'asc' | 'desc'
}
