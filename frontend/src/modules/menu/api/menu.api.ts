import { api } from '@/shared/api/client'
import type { ApiResource } from '@/shared/api/types'
import type { MenuItem } from '../types/menu'

export const menuApi = {
  /**
   * Menu effectif de l'appelant : catalogue ∩ rôles ∩ permissions.
   *
   * C'est la seule route de menu qui ne passe pas par un rôle. Le **réglage**,
   * lui, vit entièrement sur la fiche du rôle — voir `roleMenu.api.ts`.
   */
  effective: () => api.get<ApiResource<MenuItem[]>>('/menu').then((response) => response.data),
}
