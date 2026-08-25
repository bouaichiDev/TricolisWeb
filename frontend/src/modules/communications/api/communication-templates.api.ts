import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { CommunicationTemplate } from '../types/communication'

/** Charge utile de `StoreCommunicationTemplateRequest`. */
export interface CommunicationTemplatePayload {
  serviceId?: string | null
  code: string
  name: string
  channel: string
  templateType: string
  subjectTemplate?: string | null
  bodyTemplate: string
  bodyFormat?: string
  language: string
  availableVariables?: string[] | null
  isDefault?: boolean
  isActive?: boolean
}

export interface CommunicationTemplateFilters {
  page: number
  perPage: number
  search?: string
  channel?: string
  templateType?: string
  language?: string
  isActive?: boolean
  sort?: string
  direction?: 'asc' | 'desc'
}

export const communicationTemplatesApi = {
  list: (filters: CommunicationTemplateFilters) =>
    api.get<ApiCollection<CommunicationTemplate>>('/communication-templates', {
      query: { ...filters },
    }),

  get: (id: string) =>
    api
      .get<ApiResource<CommunicationTemplate>>(`/communication-templates/${id}`)
      .then((response) => response.data),

  create: (payload: CommunicationTemplatePayload) =>
    api
      .post<ApiResource<CommunicationTemplate>>('/communication-templates', payload)
      .then((response) => response.data),

  update: (id: string, payload: Partial<CommunicationTemplatePayload>) =>
    api
      .patch<ApiResource<CommunicationTemplate>>(`/communication-templates/${id}`, payload)
      .then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/communication-templates/${id}`),
}
