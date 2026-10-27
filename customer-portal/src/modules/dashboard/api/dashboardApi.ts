import { api } from '@/shared/api/client'
import type { ApiEnvelope } from '@/shared/api/types'

import type { DashboardPeriod, DashboardResponse } from '../types/dashboard'

export const dashboardApi = {
  get: async (period: DashboardPeriod | null) =>
    (await api.get<ApiEnvelope<DashboardResponse>>('/dashboard', { query: { from: period?.from, to: period?.to } })).data,
}
