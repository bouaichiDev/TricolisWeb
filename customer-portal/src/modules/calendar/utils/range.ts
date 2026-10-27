/**
 * Une date locale au format `AAAA-MM-JJ`.
 *
 * Pas `toISOString()` : elle convertit en UTC, et minuit à Lausanne deviendrait
 * la veille à 22 h — la période demandée glisserait d'un jour.
 */
export function toDateOnly(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${date.getFullYear()}-${month}-${day}`
}
