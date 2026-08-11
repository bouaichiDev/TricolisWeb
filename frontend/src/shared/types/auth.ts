/**
 * Types d'identité et d'habilitation.
 *
 * Relevés sur la réponse réelle de `/api/v1/auth/me`, pas déduits. Le §1 de la
 * phase l'impose : aucun champ inventé.
 *
 * Le point structurant : **les habilitations sont portées par l'appartenance**,
 * pas par l'utilisateur. Un même compte peut être propriétaire d'une
 * organisation et simple lecteur dans une autre. Changer d'organisation change
 * donc les rôles, les permissions et les agences accessibles.
 */

export interface AuthRole {
  id: string
  code: string
  name: string
}

export interface AuthPermission {
  id: string
  code: string
}

export interface AuthAgency {
  id: string
  code: string
  name: string
}

/** Appartenance de l'utilisateur à une organisation, avec ses droits. */
export interface AuthMembership {
  id: string
  code: string
  name: string
  isOwner: boolean
  isPrimary: boolean
  roles: AuthRole[]
  permissions: AuthPermission[]
  agencies: AuthAgency[]
}

export interface AuthUser {
  id: string
  firstName: string
  lastName: string
  fullName: string
  email: string
  phone: string | null
  preferredLanguage: string
  status: string
  emailVerifiedAt: string | null
  lastLoginAt: string | null
  createdAt: string
  updatedAt: string
  organizations: AuthMembership[]
}

export interface LoginPayload {
  user: AuthUser
  token: string
}

export interface MePayload {
  user: AuthUser
}

/**
 * Ce que le reste de l'application consomme.
 *
 * `permissions` est aplati en codes — c'est la seule forme utile aux gardes —
 * mais il vient toujours de l'appartenance courante, jamais d'une liste écrite
 * en dur.
 */
export interface AuthContextValue {
  user: AuthUser | null
  memberships: AuthMembership[]
  membership: AuthMembership | null
  organizationId: string | null
  roles: AuthRole[]
  permissions: string[]
  agencies: AuthAgency[]
  isOwner: boolean
  isAuthenticated: boolean
  isLoading: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
  switchOrganization: (organizationId: string) => void
}
