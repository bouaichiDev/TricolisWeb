import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'

import type { Tour, TourFilters, TourPayload } from '../types/tour'

export const toursApi = {
  list: (filters: TourFilters) =>
    api.get<ApiCollection<Tour>>('/tours', { query: { ...filters } }),

  get: (id: string) => api.get<ApiResource<Tour>>(`/tours/${id}`).then((r) => r.data),

  create: (payload: TourPayload) =>
    api.post<ApiResource<Tour>>('/tours', payload).then((r) => r.data),

  update: (id: string, payload: TourPayload) =>
    api.patch<ApiResource<Tour>>(`/tours/${id}`, payload).then((r) => r.data),

  remove: (id: string) => api.delete<void>(`/tours/${id}`),

  /**
   * Réserver la tournée pour la composer.
   *
   * **Explicite, et non prise à chaque planification.** C'est la carte qui
   * réserve, parce que c'est elle qui cache son travail jusqu'à confirmation ;
   * un glisser-déposé depuis les colonnes agit tout de suite.
   */
  reserve: (id: string) => api.post<void>(`/tours/${id}/reserve`),

  /**
   * Rendre la tournée : la composition est terminée.
   *
   * **Le statut n'est pas touché.** Confirmer ses modifications dans la carte
   * ne confirme pas la tournée : elle reste au brouillon, avec ce qu'on y a mis.
   */
  release: (id: string) => api.post<void>(`/tours/${id}/release`),

  /**
   * Le tracé routier entre les arrêts.
   *
   * Rendu vide quand aucun fournisseur de géométrie n'est déclaré ou qu'un
   * arrêt n'est pas géocodé : la carte retombe alors sur ses segments à vol
   * d'oiseau, et le dit.
   */
  routeGeometry: (id: string) =>
    api
      .get<ApiResource<{ points: [number, number][] }>>(`/tours/${id}/route-geometry`)
      .then((response) => response.data.points),

  /**
   * Faire passer la tournée d'un état à un autre.
   *
   * C'est par là que se valide un brouillon et qu'il s'annule : le référentiel
   * dit quels passages existent, le serveur les applique.
   */
  changeStatus: (id: string, status: string) =>
    api.post<ApiResource<Tour>>(`/tours/${id}/status`, { status }).then((r) => r.data),
}
