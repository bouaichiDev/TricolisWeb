import { z } from 'zod'

import type { StockMovementPayload } from '../api/stock-movements.api'

/**
 * Mouvement de stock.
 *
 * Les deux règles structurelles de `CreateStockMovementAction` sont reprises
 * ici — au moins une extrémité, et les deux différentes — pour que la saisie
 * soit refusée avant l'aller-retour. **Le serveur reste l'autorité** : lui seul
 * connaît le disponible, et il le lit sous verrou.
 *
 * `movementType` n'est pas énuméré. Le diagramme n'en définit aucune valeur, le
 * backend ne l'interprète pas, et aucune table ne les référence : coder une
 * liste ici inventerait un contrat qui n'existe pas.
 *
 * **Ni `sourceEntityType` ni `sourceEntityId`.** Ces deux champs désignent
 * l'origine d'un mouvement, et le serveur les renseigne lui-même : c'est
 * `ConsumeOrderStock` qui écrit `MorphMap::ORDER_LINE` quand une commande
 * consomme du stock. Un mouvement saisi à la main n'a pas d'origine à déclarer.
 * Les proposer supposerait par ailleurs de connaître la liste blanche
 * `MorphMap::registered()`, qu'aucune route n'expose — et le §21 interdit d'en
 * inventer une.
 */
export const stockMovementSchema = z
  .object({
    stockItemId: z.string().min(1, 'validation.required'),
    sourceLocationId: z.string(),
    destinationLocationId: z.string(),
    movementType: z.string().min(1, 'validation.required').max(64, 'validation.max'),
    quantity: z
      .string()
      .min(1, 'validation.required')
      .refine((value) => Number(value) > 0, 'stock.quantityMustBePositive'),
  })
  .refine(
    (values) => values.sourceLocationId !== '' || values.destinationLocationId !== '',
    { message: 'stock.needsOneLocation', path: ['sourceLocationId'] },
  )
  .refine(
    (values) =>
      values.sourceLocationId === '' ||
      values.sourceLocationId !== values.destinationLocationId,
    { message: 'stock.sameLocation', path: ['destinationLocationId'] },
  )

export type StockMovementFormValues = z.infer<typeof stockMovementSchema>

export const STOCK_MOVEMENT_FORM_DEFAULTS: StockMovementFormValues = {
  stockItemId: '',
  sourceLocationId: '',
  destinationLocationId: '',
  movementType: '',
  quantity: '',
}

const blank = (value: string): string | null => (value.trim() === '' ? null : value.trim())

export function toStockMovementPayload(values: StockMovementFormValues): StockMovementPayload {
  return {
    stockItemId: values.stockItemId,
    sourceLocationId: blank(values.sourceLocationId),
    destinationLocationId: blank(values.destinationLocationId),
    movementType: values.movementType.trim(),
    quantity: Number(values.quantity),
  }
}
