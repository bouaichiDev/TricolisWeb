import { api } from '@/shared/api/client'
import type { ApiResource } from '@/shared/api/types'
import type { MenuItem } from '@/modules/menu/types/menu'

/**
 * Ce qu'un rôle peut régler sur une entrée.
 *
 * **Tout**, sauf la destination : chaque rôle porte son menu entier — ordre,
 * nom, icône, groupe, visibilité. Ni `route` ni `permission` ne s'écrivent ici,
 * elles vivent en code et les saisir permettrait de fabriquer un menu qui mène
 * à « Page introuvable ».
 *
 * `label` et `icon` acceptent `null` : c'est le geste qui revient au catalogue.
 * Un champ **absent** laisse au contraire le réglage en place — la distinction
 * est tenue par le backend, et envoyer `undefined` n'efface donc rien.
 *
 * `parent` n'accepte qu'un groupe, livré ou créé, ou `null` pour le premier
 * niveau : la barre latérale rend deux niveaux, pas trois.
 */
export interface RoleMenuUpdateItem {
  code: string
  isVisible?: boolean
  position?: number
  label?: string | null
  icon?: string | null
  parent?: string | null
}

export const roleMenuApi = {
  /** Catalogue configurable, avec l'état choisi par ce rôle. */
  get: (roleId: string) =>
    api.get<ApiResource<MenuItem[]>>(`/roles/${roleId}/menu`).then((response) => response.data),

  update: (roleId: string, items: RoleMenuUpdateItem[]) =>
    api
      .patch<ApiResource<MenuItem[]>>(`/roles/${roleId}/menu`, { items })
      .then((response) => response.data),

  /**
   * Crée un groupe. Un nom et une icône suffisent : un groupe n'ouvre rien,
   * c'est un titre au-dessus d'entrées qui gardent leur destination.
   *
   * Les trois écritures renvoient le réglage entier, ce qui évite de recharger
   * après coup — et de montrer un instant un menu sans le groupe qu'on vient
   * de créer.
   */
  createGroup: (roleId: string, group: { label: string; icon: string }) =>
    api
      .post<ApiResource<MenuItem[]>>(`/roles/${roleId}/menu/groups`, group)
      .then((response) => response.data),

  deleteGroup: (roleId: string, code: string) =>
    api
      .delete<ApiResource<MenuItem[]>>(`/roles/${roleId}/menu/groups/${code}`)
      .then((response) => response.data),
}
