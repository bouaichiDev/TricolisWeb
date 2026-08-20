import type { AsyncOption } from '@/shared/components/form/AsyncSelect'

import { useStockLocations } from './useStock'
import type { StockLocation } from '../types/stock'

/**
 * Libellé d'un emplacement : son code, précédé de sa zone quand il en a une.
 *
 * `location_code` est unique **par dépôt**, pas globalement. Deux dépôts
 * peuvent donc afficher « A-01-2 » ; la zone lève l'ambiguïté la plus courante
 * sans imposer de charger le dépôt de chaque ligne.
 */
export function locationLabel(location: StockLocation): string {
  return location.zoneCode === null || location.zoneCode === ''
    ? location.locationCode
    : `${location.zoneCode} · ${location.locationCode}`
}

/**
 * Emplacements proposables à un mouvement.
 *
 * Seuls les emplacements actifs : `CreateStockMovementAction` accepte les
 * autres, mais ranger de la marchandise dans un emplacement fermé n'a pas de
 * sens, et l'écran n'a pas à le suggérer.
 */
export function useStockLocationOptions(depotId?: string) {
  const query = useStockLocations({ page: 1, perPage: 200, status: 'active', depotId })

  return {
    isLoading: query.isPending,
    options: (query.data?.data ?? []).map(
      (location): AsyncOption => ({
        value: location.id,
        label: locationLabel(location),
        hint: location.barcode ?? undefined,
      }),
    ),
    byId: new Map((query.data?.data ?? []).map((location) => [location.id, location])),
  }
}
