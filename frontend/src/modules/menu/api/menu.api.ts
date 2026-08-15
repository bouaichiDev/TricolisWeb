import { api } from '@/shared/api/client'
import type { ApiResource } from '@/shared/api/types'
import type { MenuItem } from '../types/menu'

export interface MenuUpdateItem {
  code: string
  isVisible?: boolean
  position?: number
}

export const menuApi = {
  /** Menu effectif : catalogue ∩ réglages de l'organisation ∩ permissions. */
  effective: () => api.get<ApiResource<MenuItem[]>>('/menu').then((response) => response.data),

  /** Catalogue configurable, non filtré par les permissions de l'appelant. */
  catalogue: () =>
    api.get<ApiResource<MenuItem[]>>('/menu/catalogue').then((response) => response.data),

  update: (items: MenuUpdateItem[]) =>
    api.patch<ApiResource<MenuItem[]>>('/menu', { items }).then((response) => response.data),
}
