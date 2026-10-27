/**
 * Accès au portail fournisseur — `ProviderPortalUserResource`.
 *
 * Géré **uniquement** ici, au back-office : le portail fournisseur n'a aucun
 * écran de gestion de comptes ni de rôles.
 */
export interface ProviderPortalUser {
  id: string
  status: 'invited' | 'active' | 'suspended' | 'disabled'
  statusLabel: string
  invitedAt: string | null
  activatedAt: string | null
  disabledAt: string | null
  user: {
    id: string
    firstName: string | null
    lastName: string | null
    email: string
    lastLoginAt: string | null
  }
  roles: ProviderPortalRole[]
}

export interface ProviderPortalRole {
  id: string
  code: string
  name: string
}

export interface ProviderPortalInvitePayload {
  email: string
  firstName: string
  lastName: string
  roleId: string
}

export type ProviderPortalUserAction = 'disable' | 'enable' | 'resend-invitation'
