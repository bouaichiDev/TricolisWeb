import { formatDate, formatDateTime } from './format'

const timeOnly = new Intl.DateTimeFormat('fr-CH', { timeStyle: 'short' })

/** Une plage de dates telle que le backend la rend (calendrier, suivi, lien public). */
export interface Slot {
  start: string
  end: string | null
  allDay: boolean
  source: 'planned' | 'requested' | string
}

/**
 * « 29 sept. 2026, 09:00 – 11:00 », ou la date seule pour une journée entière.
 * Les heures sont des heures murales, sans décalage : elles s'affichent telles
 * quelles, comme le transporteur les a planifiées.
 */
export function formatSlot(slot: Slot | null | undefined): string {
  if (!slot) return '—'
  if (slot.allDay) return formatDate(slot.start)

  const start = formatDateTime(slot.start)
  if (!slot.end) return start

  return `${start} – ${timeOnly.format(new Date(slot.end))}`
}

/** Ce que la date représente, dit au client. */
export function slotSourceLabel(source: string): string {
  return source === 'planned' ? 'Planifié par votre transporteur' : 'Date demandée'
}
