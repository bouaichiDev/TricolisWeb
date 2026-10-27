import type { CustomerContext, PortalUser } from '@/shared/api/types'

export function makeContext(permissions: string[] = [], overrides: Partial<CustomerContext> = {}): CustomerContext {
  return {
    user: {
      id: '01USER',
      firstName: 'Ingrid',
      lastName: 'Admin',
      email: 'admin@ikea.test',
      phone: null,
      preferredLanguage: 'fr',
    },
    customer: {
      id: '01IKEA',
      code: 'ikea',
      name: 'IKEA',
      legalName: null,
      email: null,
      phone: null,
      features: { catalog: true, stock: true, tracking: true, packages: false, appointments: false },
    },
    carrier: { id: '01ORG', name: 'Tricolis Dev' },
    membership: { id: '01MEMBER', isOwner: true, status: 'active' },
    roles: [{ id: '01ROLE', code: 'CUSTOMER_ADMIN', name: 'Administrateur client' }],
    permissions,
    ...overrides,
  }
}

export const ADMIN_PERMISSIONS = [
  'customer.users.view',
  'customer.users.invite',
  'customer.users.update',
  'customer.users.disable',
  'customer.users.assign_role',
  'customer.roles.view',
  'customer.orders.view',
  'customer.stock.view',
]

export function makeUser(overrides: Partial<PortalUser> = {}): PortalUser {
  return {
    id: '01VIEWER',
    status: 'active',
    statusLabel: 'Actif',
    isOwner: false,
    invitedAt: null,
    activatedAt: '2026-09-01T10:00:00+00:00',
    disabledAt: null,
    user: {
      id: '01U2',
      firstName: 'Vera',
      lastName: 'Lecture',
      email: 'viewer@ikea.test',
      phone: null,
      lastLoginAt: null,
    },
    roles: [{ id: '01R2', code: 'CUSTOMER_VIEWER', name: 'Lecteur' }],
    ...overrides,
  }
}
