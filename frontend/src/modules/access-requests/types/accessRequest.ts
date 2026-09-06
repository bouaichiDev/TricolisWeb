/** Demande d'accès — champs relevés sur `AccessRequestResource`. */
export interface AccessRequest {
  id: string
  companyName: string
  contactName: string
  email: string
  phone: string
  message: string | null
  status: AccessRequestStatus
  decisionNote: string | null
  decidedAt: string | null
  /** L'organisation née de la demande : elle n'existe qu'après l'acceptation. */
  organizationId: string | null
  createdAt: string
}

export const ACCESS_REQUEST_STATUSES = ['pending', 'approved', 'rejected'] as const

export type AccessRequestStatus = (typeof ACCESS_REQUEST_STATUSES)[number]

/** Ce que remplit un visiteur depuis l'écran de connexion. */
export interface AccessRequestPayload {
  companyName: string
  contactName: string
  email: string
  phone: string
  message?: string
}
