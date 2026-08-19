import { useQuery } from '@tanstack/react-query'

import { auditApi } from '../api/audit.api'
import type { AuditFilters } from '../types/auditLog'

export const auditKeys = {
  all: ['audit-logs'] as const,
  list: (filters: AuditFilters) => [...auditKeys.all, 'list', filters] as const,
}

/**
 * Journal d'audit, filtré.
 *
 * `enabled` permet de ne rien demander tant que le bloc est replié : une fiche
 * qui afficherait l'historique de vingt lignes déclencherait autrement vingt
 * requêtes que personne n'a demandées.
 */
export function useAuditLogs(filters: AuditFilters, enabled = true) {
  return useQuery({
    queryKey: auditKeys.list(filters),
    queryFn: () => auditApi.list(filters),
    enabled,
    placeholderData: (previous) => previous,
  })
}
