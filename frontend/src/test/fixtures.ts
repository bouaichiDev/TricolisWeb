import type { AuthMembership, AuthUser } from '@/shared/types/auth'

/**
 * Jeux de données de test.
 *
 * Ils reproduisent la **forme réelle** des réponses de l'API, relevée sur les
 * ressources Laravel. Un jeu de test qui invente un champ ferait passer des
 * tests que la vraie API mettrait en échec.
 */
export const ORGANIZATION_ID = '01JQZ0000000000000000ORG1'

export function makeMembership(overrides: Partial<AuthMembership> = {}): AuthMembership {
  return {
    id: ORGANIZATION_ID,
    code: 'TRICOLIS',
    name: 'Tricolis SAS',
    isOwner: false,
    isPrimary: true,
    hasLogo: false,
    roles: [
      {
        id: '01JQZ00000000000000ROLE1',
        code: 'admin',
        name: 'Administrateur',
        scope: 'organization',
        isSystem: true,
      },
    ],
    permissions: [],
    agencies: [],
    ...overrides,
  }
}

/**
 * Appartenance conférant l'autorité plateforme.
 *
 * Le rôle porte `scope: 'platform'` — la seule marque qui compte. Un rôle
 * simplement nommé « SuperAdmin » sans cette portée reste ordinaire, et les
 * tests s'appuient sur cette distinction.
 */
export function platformMembership(overrides: Partial<AuthMembership> = {}): AuthMembership {
  return makeMembership({
    roles: [
      {
        id: '01JQZ0000000000000PLATF1',
        code: 'superadmin',
        name: 'Administrateur plateforme',
        scope: 'platform',
        isSystem: true,
      },
    ],
    ...overrides,
  })
}

/** Raccourci : la liste de codes est ce que les gardes consomment réellement. */
export function withPermissions(codes: string[], overrides: Partial<AuthMembership> = {}) {
  return makeMembership({
    permissions: codes.map((code, index) => ({ id: `perm-${index}`, code })),
    ...overrides,
  })
}

export function makeUser(memberships: AuthMembership[] = [makeMembership()]): AuthUser {
  return {
    id: '01JQZ0000000000000000USR1',
    firstName: 'Badr',
    lastName: 'Ouali',
    fullName: 'Badr Ouali',
    email: 'badr@example.test',
    phone: null,
    preferredLanguage: 'fr',
    status: 'active',
    emailVerifiedAt: null,
    lastLoginAt: null,
    createdAt: '2026-01-05T09:00:00.000000Z',
    updatedAt: '2026-01-05T09:00:00.000000Z',
    organizations: memberships,
  }
}

/** Enveloppe paginée, identique à celle produite par `ApiResponse::paginated`. */
export function paginated<T>(rows: T[], overrides: Record<string, unknown> = {}) {
  return {
    data: rows,
    meta: {
      currentPage: 1,
      from: rows.length === 0 ? null : 1,
      lastPage: 1,
      perPage: 25,
      to: rows.length === 0 ? null : rows.length,
      total: rows.length,
      ...overrides,
    },
    links: { first: null, last: null, prev: null, next: null },
  }
}
