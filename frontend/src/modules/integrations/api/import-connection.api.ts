import { api } from '@/shared/api/client'
import type { ApiResource } from '@/shared/api/types'

import type {
  ConnectionTestResult,
  ImportConnectionState,
  ImportTargetFieldsCatalog,
  ImportConnectionPayload,
} from '../types/importConnection'

const base = (id: string) => `/customer-import-configurations/${id}`

/**
 * Champs cibles, connexion, test et passe manuelle d'une configuration
 * d'import. Le secret part en écriture seule et ne revient jamais.
 */
export const importConnectionApi = {
  targetFields: () =>
    api.get<ApiResource<ImportTargetFieldsCatalog>>('/customer-import-configurations/target-fields').then((response) => response.data),

  get: (id: string) => api.get<ApiResource<ImportConnectionState>>(`${base(id)}/connection`).then((response) => response.data),

  save: (id: string, payload: ImportConnectionPayload) =>
    api.put<ApiResource<ImportConnectionState>>(`${base(id)}/connection`, payload).then((response) => response.data),

  test: (id: string) => api.post<ApiResource<ConnectionTestResult>>(`${base(id)}/connection/test`).then((response) => response.data),

  run: (id: string) => api.post<ApiResource<{ queued: boolean }>>(`${base(id)}/run`).then((response) => response.data),
}
