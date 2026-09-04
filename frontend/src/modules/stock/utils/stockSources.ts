/**
 * Sources du référentiel `statuses`, telles que `MorphMap` les nomme.
 *
 * Le frontend envoie le **code** d'un statut, jamais son identifiant : la
 * colonne `status` des tables de stock est un `varchar`, pas une clé étrangère.
 * La source ne sert qu'à demander la bonne liste de codes.
 *
 * `stock_balance` et `stock_movement` sont absents, et c'est voulu : ces deux
 * tables n'ont pas de colonne `status`. Un solde est un état calculé, un
 * mouvement un fait daté — ni l'un ni l'autre n'a de cycle de vie.
 */
export const STOCK_ITEM_SOURCE = 'stock_item'
export const STOCK_LOCATION_SOURCE = 'stock_location'
export const STOCK_RESERVATION_SOURCE = 'stock_reservation'

/**
 * Quantité lisible, à partir de ce que l'API renvoie.
 *
 * Les `decimal(12,3)` arrivent en **chaînes** — `"100.500"` — parce qu'un
 * flottant les déforme. La conversion n'a lieu qu'ici, pour l'affichage, et les
 * zéros décimaux inutiles tombent : `100.500` se lit `100`, `2.250` se lit
 * `2,25`. La valeur d'origine n'est jamais réécrite.
 */
export function formatStockQuantity(value: number | string | null | undefined): string {
  if (value === null || value === undefined || value === '') return '—'

  const amount = typeof value === 'number' ? value : Number.parseFloat(value)
  if (Number.isNaN(amount)) return '—'

  return Number.isInteger(amount) ? String(amount) : String(Number(amount.toFixed(3)))
}

/** Somme d'une colonne de quantités, en repassant par les nombres une seule fois. */
export function sumQuantities(values: (number | string)[]): number {
  return values.reduce<number>((total, value) => {
    const amount = typeof value === 'number' ? value : Number.parseFloat(value)

    return total + (Number.isNaN(amount) ? 0 : amount)
  }, 0)
}
