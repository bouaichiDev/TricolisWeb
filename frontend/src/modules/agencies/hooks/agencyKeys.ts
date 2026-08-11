import type { AgencyFilters } from '../types/agency'

export const agencyKeys = {
  all: ['agencies'] as const,
  lists: () => [...agencyKeys.all, 'list'] as const,
  list: (filters: AgencyFilters) => [...agencyKeys.lists(), filters] as const,
  details: () => [...agencyKeys.all, 'detail'] as const,
  detail: (id: string) => [...agencyKeys.details(), id] as const,
}
