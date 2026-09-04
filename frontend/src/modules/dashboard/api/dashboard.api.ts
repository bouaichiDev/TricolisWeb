import { api } from '@/shared/api/client'
import type { ApiResource } from '@/shared/api/types'
import type { DashboardResponse } from '../types/dashboard'

/**
 * Le tableau de bord, en **un seul appel**.
 *
 * L'écran précédent demandait une page d'un élément à quatre listes paginées
 * pour n'en lire que `meta.total` : c'était le seul chiffre que le backend
 * offrait, et cela coûtait quatre requêtes HTTP pour quatre entiers. Avec une
 * cinquantaine de widgets configurables, la même méthode aurait fini par ouvrir
 * une requête par carte.
 *
 * Ce qui revient est déjà filtré par les permissions : rien à retirer ici, et
 * surtout rien à décider.
 */
export const dashboardApi = {
  current: () =>
    api.get<ApiResource<DashboardResponse>>('/dashboard').then((response) => response.data),
}
