import type { ProviderContext } from '@/shared/api/types'

export function makeContext(permissions: string[] = [], overrides: Partial<ProviderContext> = {}): ProviderContext {
  return {
    user: {
      id: '01USER',
      firstName: 'Alice',
      lastName: 'Admin',
      email: 'admin@transports-alpha.test',
    },
    provider: {
      id: '01ALPHA',
      code: 'transports-alpha',
      name: 'Transports Alpha',
    },
    carrier: { name: 'Tricolis Dev' },
    roles: [{ code: 'PROVIDER_ADMIN', name: 'Administrateur fournisseur' }],
    permissions,
    ...overrides,
  }
}

export const ADMIN_PERMISSIONS = ['provider.portal.access', 'provider.dashboard.view', 'provider.profile.view']
