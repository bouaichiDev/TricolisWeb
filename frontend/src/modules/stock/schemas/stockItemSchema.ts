import { z } from 'zod'

import type { StockItemPayload, StockItemUpdatePayload } from '../api/stock-items.api'
import type { StockItemDetail } from '../types/stock'

/**
 * Longueurs reprises de `StoreStockItemRequest`.
 *
 * **Aucun champ de quantité.** Les quantités appartiennent à `StockBalance`,
 * par emplacement, et se déplacent par mouvement ; en poser une ici créerait
 * une seconde vérité que rien ne tiendrait à jour.
 *
 * `customerId` n'est demandé qu'à la création : `UpdateStockItemRequest` ne le
 * connaît pas, un article ne change pas de client.
 */
export const stockItemSchema = z.object({
  customerId: z.string().min(1, 'validation.required'),
  catalogItemId: z.string(),
  articleCode: z.string().min(1, 'validation.required').max(64, 'validation.max'),
  barcode: z.string().max(128, 'validation.max'),
  description: z.string().max(255, 'validation.max'),
  status: z.string().min(1, 'validation.required'),
})

export type StockItemFormValues = z.infer<typeof stockItemSchema>

export const STOCK_ITEM_FORM_DEFAULTS: StockItemFormValues = {
  customerId: '',
  catalogItemId: '',
  articleCode: '',
  barcode: '',
  description: '',
  status: 'active',
}

/**
 * Un champ vide part en `null`, pas en chaîne vide.
 *
 * `stock_items` porte `unique(customer_id, barcode)` : deux articles sans
 * code-barres enregistrés à `''` entreraient en collision, alors que deux
 * `NULL` cohabitent. La distinction n'est pas cosmétique.
 */
const blank = (value: string): string | null => (value.trim() === '' ? null : value.trim())

export function toStockItemPayload(values: StockItemFormValues): StockItemPayload {
  return {
    customerId: values.customerId,
    catalogItemId: blank(values.catalogItemId),
    articleCode: values.articleCode.trim(),
    barcode: blank(values.barcode),
    description: blank(values.description),
    status: values.status,
  }
}

/** Le client est retiré : le serveur refuserait le champ, et le déplacement. */
export function toStockItemUpdatePayload(values: StockItemFormValues): StockItemUpdatePayload {
  const { customerId: _customerId, ...rest } = toStockItemPayload(values)

  return rest
}

export function toStockItemFormValues(item: StockItemDetail): StockItemFormValues {
  return {
    customerId: item.customerId,
    catalogItemId: item.catalogItemId ?? '',
    articleCode: item.articleCode,
    barcode: item.barcode ?? '',
    description: item.description ?? '',
    status: item.status,
  }
}
