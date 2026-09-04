/** Entrée d'audit — champs relevés sur `AuditLogResource`. */
export interface AuditLog {
  id: string
  organizationId: string
  userId: string | null
  action: string
  entityType: string
  entityId: string
  oldValues: Record<string, unknown> | null
  newValues: Record<string, unknown> | null
  ipAddress: string | null
  createdAt: string
}

/** Filtres acceptés par `AuditLogController::index`, et eux seuls. */
export interface AuditFilters {
  page: number
  perPage: number
  userId?: string
  action?: string
  entityType?: string
  entityId?: string
  createdFrom?: string
  createdTo?: string
}

/**
 * Champs modifiés entre l'avant et l'après.
 *
 * Le journal enregistre deux instantanés complets ; afficher les deux en entier
 * noierait la modification réelle sous les colonnes inchangées.
 */
export interface AuditChange {
  key: string
  before: unknown
  after: unknown
}

export function diffAuditValues(log: AuditLog): AuditChange[] {
  const keys = new Set([
    ...Object.keys(log.oldValues ?? {}),
    ...Object.keys(log.newValues ?? {}),
  ])

  const changes: AuditChange[] = []

  for (const key of [...keys].sort()) {
    const before = log.oldValues?.[key]
    const after = log.newValues?.[key]
    if (JSON.stringify(before) !== JSON.stringify(after)) {
      changes.push({ key, before, after })
    }
  }

  return changes
}

export function formatAuditValue(value: unknown): string {
  if (value === null || value === undefined) return '—'
  if (typeof value === 'string') return value
  if (typeof value === 'number' || typeof value === 'boolean') return String(value)

  return JSON.stringify(value)
}
