import { z } from 'zod'

import type { StockLocationPayload, StockLocationUpdatePayload } from '../api/stock-locations.api'
import type { StockLocationDetail } from '../types/stock'

/**
 * Longueurs reprises de `StoreStockLocationRequest`.
 *
 * **Pas de `customerId`.** Un emplacement est une étagère : il appartient au
 * dépôt du transporteur, et la marchandise de plusieurs clients peut y passer.
 * C'est `StockBalance` qui rattache une quantité à un article, donc à un client.
 */
export const stockLocationSchema = z.object({
  depotId: z.string().min(1, 'validation.required'),
  parentLocationId: z.string(),
  zoneCode: z.string().max(64, 'validation.max'),
  aisle: z.string().max(32, 'validation.max'),
  rack: z.string().max(32, 'validation.max'),
  level: z.string().max(32, 'validation.max'),
  locationCode: z.string().min(1, 'validation.required').max(64, 'validation.max'),
  barcode: z.string().max(128, 'validation.max'),
  status: z.string().min(1, 'validation.required'),
})

export type StockLocationFormValues = z.infer<typeof stockLocationSchema>

export const STOCK_LOCATION_FORM_DEFAULTS: StockLocationFormValues = {
  depotId: '',
  parentLocationId: '',
  zoneCode: '',
  aisle: '',
  rack: '',
  level: '',
  locationCode: '',
  barcode: '',
  status: 'active',
}

/** `unique(depot_id, barcode)` : deux `NULL` cohabitent, deux `''` non. */
const blank = (value: string): string | null => (value.trim() === '' ? null : value.trim())

export function toStockLocationPayload(values: StockLocationFormValues): StockLocationPayload {
  return {
    depotId: values.depotId,
    parentLocationId: blank(values.parentLocationId),
    zoneCode: blank(values.zoneCode),
    aisle: blank(values.aisle),
    rack: blank(values.rack),
    level: blank(values.level),
    locationCode: values.locationCode.trim(),
    barcode: blank(values.barcode),
    status: values.status,
  }
}

/** Le dépôt est retiré : un emplacement physique ne déménage pas. */
export function toStockLocationUpdatePayload(
  values: StockLocationFormValues,
): StockLocationUpdatePayload {
  const { depotId: _depotId, ...rest } = toStockLocationPayload(values)

  return rest
}

export function toStockLocationFormValues(
  location: StockLocationDetail,
): StockLocationFormValues {
  return {
    depotId: location.depotId,
    parentLocationId: location.parentLocationId ?? '',
    zoneCode: location.zoneCode ?? '',
    aisle: location.aisle ?? '',
    rack: location.rack ?? '',
    level: location.level ?? '',
    locationCode: location.locationCode,
    barcode: location.barcode ?? '',
    status: location.status,
  }
}
