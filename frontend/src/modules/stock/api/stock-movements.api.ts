import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type { StockMovement, StockMovementDetail } from '../types/stock'
import type { StockMovementFilters } from '../types/stockFilters'

/**
 * Charge utile relevée sur `StoreStockMovementRequest`.
 *
 * `movementType` est une chaîne libre : le backend ne l'interprète pas, et
 * `CreateStockMovementAction` le dit — « aucun type de mouvement n'est
 * interprété : le diagramme n'en énumère aucun ».
 *
 * Ce qui est contrôlé est structurel : au moins une source ou une destination,
 * les deux différentes, et le même dépôt de part et d'autre.
 *
 * `sourceEntityType` n'accepte que les clés de `MorphMap::registered()`, et
 * `sourceEntityId` devient obligatoire dès qu'il est fourni.
 */
export interface StockMovementPayload {
  stockItemId: string
  sourceLocationId?: string | null
  destinationLocationId?: string | null
  movementType: string
  quantity: number
  sourceEntityType?: string | null
  sourceEntityId?: string | null
}

/**
 * Mouvements de stock — **création et lecture, rien d'autre**.
 *
 * `Route::apiResource(...)->only(['index', 'store', 'show'])` : il n'existe ni
 * `PATCH` ni `DELETE`. Un mouvement est un fait daté ; le corriger reviendrait à
 * réécrire l'histoire d'un solde, et une correction s'enregistre comme un
 * mouvement de plus.
 */
export const stockMovementsApi = {
  list: (filters: StockMovementFilters) =>
    api.get<ApiCollection<StockMovement>>('/stock-movements', { query: { ...filters } }),

  get: (id: string) =>
    api
      .get<ApiResource<StockMovementDetail>>(`/stock-movements/${id}`)
      .then((response) => response.data),

  /**
   * Un transfert est **une** requête.
   *
   * Débiter puis créditer en deux appels laisserait une fenêtre où la
   * marchandise n'existe nulle part, et un échec du second appel la ferait
   * disparaître. `CreateStockMovementAction` verrouille les deux soldes dans une
   * transaction unique ; un stock insuffisant sort en 409.
   */
  create: (payload: StockMovementPayload) =>
    api
      .post<ApiResource<StockMovementDetail>>('/stock-movements', payload)
      .then((response) => response.data),
}
