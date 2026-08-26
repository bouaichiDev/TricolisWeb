/**
 * Membre = rattachement `OrganizationUser`, champs relevés sur
 * `OrganizationUserResource`.
 *
 * C'est le rattachement qui porte les rôles, jamais l'utilisateur global : un
 * même compte peut être administrateur dans une organisation et simple lecteur
 * dans une autre. L'écran d'administration travaille donc sur cette ressource.
 */
export interface MemberRole {
  id: string
  code: string
  name: string
}

export interface Member {
  id: string
  organizationId: string
  userId: string
  isOwner: boolean
  isPrimary: boolean
  status: string
  joinedAt: string | null
  user: {
    firstName: string
    lastName: string
    email: string
    phone: string | null
    preferredLanguage: string | null
  }
  roles: MemberRole[]
  /**
   * Le chauffeur que ce membre est, s'il en est un.
   *
   * Le lien se lit dans les deux sens : la fiche du chauffeur mène à son
   * compte, celle du compte mène au chauffeur.
   */
  driver: { id: string; code: string; name: string } | null
}

/** Valeurs de `UserStatus` côté API. */
export const USER_STATUSES = ['invited', 'active', 'suspended', 'disabled'] as const

export interface MemberFilters {
  page: number
  perPage: number
  search?: string
}

export function memberFullName(member: Member): string {
  return `${member.user.firstName} ${member.user.lastName}`.trim()
}
