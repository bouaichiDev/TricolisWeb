import { api } from '@/shared/api/client'
import type { ApiEnvelope } from '@/shared/api/types'

/** Les chiffres du tableau de bord, tous calculés par le serveur. */
export interface ProviderDashboard {
  toursToday: number
  toursInProgress: number
  toursCompletedToday: number
  activeDrivers: number
  activeVehicles: number
}

export const dashboardApi = {
  get: async () => (await api.get<ApiEnvelope<ProviderDashboard>>('/dashboard')).data,
}
