import type { OrganizationFilters } from '../types/organization'

/** Fabrique de clés de cache : un seul endroit sait comment une liste est indexée. */
export const organizationKeys = {
  all: ['organizations'] as const,
  lists: () => [...organizationKeys.all, 'list'] as const,
  list: (filters: OrganizationFilters) => [...organizationKeys.lists(), filters] as const,
  detail: (id: string) => [...organizationKeys.all, 'detail', id] as const,
}
