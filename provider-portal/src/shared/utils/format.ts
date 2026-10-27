const dateTime = new Intl.DateTimeFormat('fr-CH', { dateStyle: 'medium', timeStyle: 'short' })
const dateOnly = new Intl.DateTimeFormat('fr-CH', { dateStyle: 'medium' })

const DATE_ONLY = /^(\d{4})-(\d{2})-(\d{2})$/

/**
 * `new Date('2026-10-12')` lit une date seule comme minuit **UTC** : à l'ouest
 * de Greenwich, elle s'afficherait la veille. Une date seule (journée entière,
 * date demandée) est un jour du calendrier : on la construit en heure locale.
 */
function parse(value: string | null | undefined): Date | null {
  if (!value) return null

  const day = DATE_ONLY.exec(value)
  const date = day ? new Date(Number(day[1]), Number(day[2]) - 1, Number(day[3])) : new Date(value)

  return Number.isNaN(date.getTime()) ? null : date
}

/** Date et heure lisibles, ou un tiret quand la valeur manque. */
export function formatDateTime(value: string | null | undefined): string {
  const date = parse(value)

  return date === null ? '—' : dateTime.format(date)
}

/** Date seule, ou un tiret. */
export function formatDate(value: string | null | undefined): string {
  const date = parse(value)

  return date === null ? '—' : dateOnly.format(date)
}

/** Taille de fichier lisible. */
export function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} o`
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} Ko`

  return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`
}
