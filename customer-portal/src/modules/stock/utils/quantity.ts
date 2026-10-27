/**
 * Une quantité du backend (« 1234.500 ») lisible à la française (« 1 234,5 »).
 *
 * Travaille sur la chaîne, jamais sur un flottant : 0,1 + 0,2 ne devient pas
 * 0,30000000000000004, et une quantité à trois décimales reste exacte.
 */
export function formatQuantity(value: string | null | undefined): string {
  if (value === null || value === undefined || value === '') return '—'

  const negative = value.startsWith('-')
  const [integer = '0', fraction = ''] = value.replace('-', '').split('.')
  const grouped = integer.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ' ')
  const decimals = fraction.replace(/0+$/, '')

  return `${negative ? '-' : ''}${grouped}${decimals ? `,${decimals}` : ''}`
}

/** Libellé du sens d'un mouvement, déduit par le backend des emplacements. */
export const DIRECTION_LABELS: Record<string, string> = { entry: 'Entrée', exit: 'Sortie', transfer: 'Transfert' }
