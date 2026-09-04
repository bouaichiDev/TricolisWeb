import { api } from '@/shared/api/client'
import type { ApiCollection } from '@/shared/api/types'
import type { AuditFilters, AuditLog } from '../types/auditLog'

/**
 * Le journal est en lecture seule : l'API n'expose ni écriture, ni modification,
 * ni suppression. C'est ce qui lui donne sa valeur de preuve.
 */
export const auditApi = {
  list: (filters: AuditFilters) =>
    api.get<ApiCollection<AuditLog>>('/audit-logs', { query: { ...filters } }),
}
