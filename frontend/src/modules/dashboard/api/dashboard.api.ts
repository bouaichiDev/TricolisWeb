import { api } from '@/shared/api/client'
import type { ApiResource } from '@/shared/api/types'
import type { DashboardPeriod, DashboardResponse } from '../types/dashboard'

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
 *
 * La période part **entière ou pas du tout**. Une seule borne serait refusée en
 * 422 par le serveur, qui ne complète pas une période à moitié saisie : la
 * borne manquante aurait filtré sur un intervalle que personne n'a choisi.
 */
export const dashboardApi = {
  current: (period: DashboardPeriod | null) =>
    api
      .get<ApiResource<DashboardResponse>>('/dashboard', {
        query: period === null ? undefined : { from: period.from, to: period.to },
      })
      .then((response) => response.data),
}
