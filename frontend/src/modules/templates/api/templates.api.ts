import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { Template } from '../types/template'

/** Charge utile de `StoreTemplateRequest`. */
export interface TemplatePayload {
  customerId?: string | null
  serviceId?: string | null
  code: string
  name: string
  channel?: string | null
  templateType: string
  subjectTemplate?: string | null
  bodyTemplate: string
  bodyFormat?: string
  language: string
  availableVariables?: string[] | null
  isDefault?: boolean
  isActive?: boolean
}

/**
 * Filtres de `/api/v1/templates`.
 *
 * `customerId` accepte la sentinelle `global` en plus d'un identifiant : sans
 * elle, « les modèles du transporteur » et « tous les modèles » se
 * demanderaient de la même façon.
 */
export interface TemplateFilters {
  page: number
  perPage: number
  search?: string
  customerId?: string
  serviceId?: string
  channel?: string
  templateType?: string
  language?: string
  isDefault?: boolean
  isActive?: boolean
  sort?: string
  direction?: 'asc' | 'desc'
}

/** Sentinelle du filtre : les modèles sans client. */
export const GLOBAL_SCOPE = 'global'

export const templatesApi = {
  list: (filters: TemplateFilters) =>
    api.get<ApiCollection<Template>>('/templates', { query: { ...filters } }),

  get: (id: string) =>
    api.get<ApiResource<Template>>(`/templates/${id}`).then((response) => response.data),

  create: (payload: TemplatePayload) =>
    api.post<ApiResource<Template>>('/templates', payload).then((response) => response.data),

  update: (id: string, payload: Partial<TemplatePayload>) =>
    api.patch<ApiResource<Template>>(`/templates/${id}`, payload).then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/templates/${id}`),
}
