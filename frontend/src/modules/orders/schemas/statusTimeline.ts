import type { AuditLog } from '@/modules/audit/types/auditLog'
import type { Status } from '@/modules/statuses/types/status'

export interface TimelineEntry {
  /** Code du statut, ou l'action brute si le journal n'en porte pas. */
  code: string
  label: string
  /** Date d'atteinte, `null` tant que le statut n'a pas été posé. */
  date: string | null
  detail: string | null
  reached: boolean
}

const valueOf = (record: Record<string, unknown> | null, key: string): string | null => {
  const value = record?.[key]

  return typeof value === 'string' ? value : null
}

/**
 * Parcours de statuts d'un élément : ce qui est arrivé, puis ce qui peut suivre.
 *
 * **Deux sources, deux natures.** Les statuts déjà atteints se lisent dans le
 * journal d'audit — c'est un fait daté, avec son auteur. Ceux à venir se lisent
 * dans le référentiel : ce sont les transitions ouvertes depuis le statut
 * courant, donc une possibilité, pas une prévision.
 *
 * Rien n'est inventé entre les deux. Une entité dont le référentiel ne décrit
 * aucun cycle — c'est le cas des lignes et des colis tant que personne ne les a
 * saisis — n'affiche que son passé, et l'écran le dit.
 */
export function buildStatusTimeline(
  logs: AuditLog[],
  currentStatus: string | null,
  reachable: Status[],
  known: Map<string, Status>,
): TimelineEntry[] {
  const past: TimelineEntry[] = []

  // Le journal arrive du plus récent au plus ancien ; un parcours se lit dans
  // l'autre sens.
  for (const log of [...logs].reverse()) {
    const code = valueOf(log.newValues, 'status')

    if (log.action === 'created') {
      past.push({
        code: code ?? 'created',
        label: code === null ? '' : (known.get(code)?.label ?? code),
        date: log.createdAt,
        detail: 'created',
        reached: true,
      })
      continue
    }

    if (code === null) continue

    past.push({
      code,
      label: known.get(code)?.label ?? code,
      date: log.createdAt,
      detail: valueOf(log.oldValues, 'status'),
      reached: true,
    })
  }

  const seen = new Set(past.map((entry) => entry.code))

  const upcoming = reachable
    .filter((status) => status.code !== currentStatus && !seen.has(status.code))
    .map(
      (status): TimelineEntry => ({
        code: status.code,
        label: status.label,
        date: null,
        detail: null,
        reached: false,
      }),
    )

  return [...past, ...upcoming]
}
