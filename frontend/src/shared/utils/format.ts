import i18n from '@/app/i18n'

/**
 * Dates.
 *
 * L'API renvoie de l'ISO 8601 en UTC ; l'affichage suit la locale active, pas
 * celle du navigateur, pour que changer de langue change aussi le format.
 */
export function formatDateTime(value: string | null | undefined): string {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''

  return new Intl.DateTimeFormat(i18n.language, {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(date)
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''

  return new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(date)
}

/**
 * Taille de fichier lisible ; le serveur renvoie des octets.
 *
 * Partagée entre l'onglet Documents d'une commande et les preuves de livraison,
 * qui affichent la même métadonnée pour la signature et la photo.
 */
export function formatBytes(size: number): string {
  if (size < 1024) return `${size} o`
  if (size < 1024 * 1024) return `${Math.round(size / 1024)} Ko`

  return `${(size / (1024 * 1024)).toFixed(1)} Mo`
}
