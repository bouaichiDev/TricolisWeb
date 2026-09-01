import { api } from '@/shared/api/client'
import type { ApiCollection, ApiResource } from '@/shared/api/types'
import type { CommunicationRule, RuleConditions } from '../types/communicationRule'

/** Charge utile de `StoreCommunicationRuleRequest`. */
export interface CommunicationRulePayload {
  serviceId?: string | null
  templateId: string
  eventType: string
  recipientRole: string
  delayValue?: number
  delayUnit: string
  conditions?: RuleConditions | null
  isAutomatic?: boolean
  isActive?: boolean
}

export interface CommunicationRuleFilters {
  page: number
  perPage: number
  search?: string
  serviceId?: string
  templateId?: string
  eventType?: string
  recipientRole?: string
  delayUnit?: string
  isAutomatic?: boolean
  isActive?: boolean
  sort?: string
  direction?: 'asc' | 'desc'
}

export const communicationRulesApi = {
  list: (filters: CommunicationRuleFilters) =>
    api.get<ApiCollection<CommunicationRule>>('/communication-rules', { query: { ...filters } }),

  get: (id: string) =>
    api
      .get<ApiResource<CommunicationRule>>(`/communication-rules/${id}`)
      .then((response) => response.data),

  create: (payload: CommunicationRulePayload) =>
    api
      .post<ApiResource<CommunicationRule>>('/communication-rules', payload)
      .then((response) => response.data),

  update: (id: string, payload: Partial<CommunicationRulePayload>) =>
    api
      .patch<ApiResource<CommunicationRule>>(`/communication-rules/${id}`, payload)
      .then((response) => response.data),

  remove: (id: string) => api.delete<void>(`/communication-rules/${id}`),
}
