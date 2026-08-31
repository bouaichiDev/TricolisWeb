import type { AsyncOption } from '@/shared/components/form/AsyncSelect'

import { useStockItems } from './useStockItems'
import { useStockLocations } from './useStockLocations'
import type { StockLocation } from '../types/stock'

/**
 * Plafond de `ListRequest` : `'perPage' => ['max:100']`.
 *
 * Le demander plus haut ne renvoie pas plus de lignes, cela renvoie un 422 et
 * la liste reste vide — sans que rien à l'écran ne dise pourquoi.
 */
const MAX_PER_PAGE = 100

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
 * Seuls les emplacements actifs : `CreateStockMovementAction` accepterait les
 * autres, mais ranger de la marchandise dans un emplacement fermé n'a pas de
 * sens, et l'écran n'a pas à le suggérer.
 *
 * Un entrepôt en compte plus de cent, et la liste est plafonnée : `total` est
 * donc renvoyé pour que l'écran **dise** qu'il en manque, au lieu de laisser
 * croire que ce qu'il montre est tout ce qui existe. La recherche porte sur la
 * zone, l'allée, la travée, le niveau, le code et le code-barres — c'est
 * `StockLocationListQuery` qui en décide.
 */
export function useStockLocationOptions(search = '', depotId?: string) {
  const query = useStockLocations({
    page: 1,
    perPage: MAX_PER_PAGE,
    status: 'active',
    search: search.trim() === '' ? undefined : search.trim(),
    depotId,
  })

  const rows = query.data?.data ?? []
  const total = query.data?.meta.total ?? rows.length

  return {
    isLoading: query.isPending,
    options: rows.map(
      (location): AsyncOption => ({
        value: location.id,
        label: locationLabel(location),
        hint: location.barcode ?? undefined,
      }),
    ),
    /** Vrai quand le plafond cache des emplacements : il faut affiner. */
    isTruncated: total > rows.length,
    total,
    byId: new Map(rows.map((location) => [location.id, location])),
  }
}

/**
 * Emplacements proposables comme **parent**, pour une hiérarchie.
 *
 * Deux filtres, pour deux raisons différentes. Le dépôt, parce que
 * `ValidateStockLocationHierarchy` refuse un parent d'un autre dépôt — le
 * proposer ne produirait qu'un 422. L'emplacement lui-même, parce qu'il ne peut
 * pas être son propre parent.
 *
 * Les cycles plus longs — A sous B sous A — ne sont **pas** filtrés ici : les
 * détecter demanderait de descendre tout l'arbre à chaque frappe. Le serveur
 * les refuse, et c'est lui qui fait autorité.
 */
export function useParentLocationOptions(depotId: string, excludeId?: string) {
  const query = useStockLocations(
    { page: 1, perPage: MAX_PER_PAGE, depotId, status: 'active' },
    depotId !== '',
  )

  const rows = (query.data?.data ?? []).filter((location) => location.id !== excludeId)
  const total = query.data?.meta.total ?? rows.length

  return {
    isLoading: depotId !== '' && query.isPending,
    options: rows.map(
      (location): AsyncOption => ({
        value: location.id,
        label: locationLabel(location),
        hint: location.barcode ?? undefined,
      }),
    ),
    isTruncated: total > rows.length,
  }
}

/**
 * Articles de stock d'un client.
 *
 * Le client est **obligatoire** : réserver l'article d'un client pour la
 * commande d'un autre est refusé par le serveur, et proposer un tel article
 * n'aurait servi qu'à produire l'erreur.
 */
export function useStockItemOptions(customerId: string, search = '') {
  const query = useStockItems(
    {
      page: 1,
      perPage: MAX_PER_PAGE,
      customerId,
      search: search.trim() === '' ? undefined : search.trim(),
    },
    customerId !== '',
  )

  const rows = query.data?.data ?? []
  const total = query.data?.meta.total ?? rows.length

  return {
    isLoading: customerId !== '' && query.isPending,
    options: rows.map(
      (item): AsyncOption => ({
        value: item.id,
        label: item.articleCode,
        hint: item.description ?? item.barcode ?? undefined,
      }),
    ),
    isTruncated: total > rows.length,
    byId: new Map(rows.map((item) => [item.id, item])),
  }
}
