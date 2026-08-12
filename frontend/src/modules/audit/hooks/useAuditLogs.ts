import { useQuery } from '@tanstack/react-query'

import { auditApi } from '../api/audit.api'
import type { AuditFilters } from '../types/auditLog'

export const auditKeys = {
  all: ['audit-logs'] as const,
  list: (filters: AuditFilters) => [...auditKeys.all, 'list', filters] as const,
}

export function useAuditLogs(filters: AuditFilters) {
  return useQuery({
    queryKey: auditKeys.list(filters),
    queryFn: () => auditApi.list(filters),
    placeholderData: (previous) => previous,
  })
}
