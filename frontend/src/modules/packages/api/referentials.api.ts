import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { PackageReferential, ReferentialFilters, ReferentialKind } from '../types/referential'

/** Charge utile relevée sur `StoreReferentialRequest`. */
export interface ReferentialPayload {
  code: string
  name: string
  status?: string
}

/**
 * Les deux référentiels de colis partagent le même contrat.
 *
 * Le chemin est le seul paramètre : `package-types` ou
 * `package-grouping-types`. Écrire deux clients identiques n'apporterait rien
 * qu'une occasion de les laisser diverger.
 */
export const referentialsApi = {
  list: (kind: ReferentialKind, filters: ReferentialFilters) =>
    api.get<ApiCollection<PackageReferential>>(`/${kind}`, { query: { ...filters } }),

  create: (kind: ReferentialKind, payload: ReferentialPayload) =>
    api.post<ApiResource<PackageReferential>>(`/${kind}`, payload).then((r) => r.data),

  update: (kind: ReferentialKind, id: string, payload: Partial<ReferentialPayload>) =>
    api.patch<ApiResource<PackageReferential>>(`/${kind}/${id}`, payload).then((r) => r.data),

  remove: (kind: ReferentialKind, id: string) => api.delete<void>(`/${kind}/${id}`),
}
