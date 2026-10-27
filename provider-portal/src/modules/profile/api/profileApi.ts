import { api } from '@/shared/api/client'
import type { ApiEnvelope } from '@/shared/api/types'

/** `GET /profile` : la fiche du fournisseur (lecture seule) et le compte. */
export interface ProviderProfile {
  provider: {
    code: string
    name: string
    status: string
    address: { line1: string | null; line2: string | null; postalCode: string | null; city: string | null; country: string | null } | null
    contact: { firstName: string | null; lastName: string | null; email: string | null; phone: string | null } | null
  }
  user: { firstName: string; lastName: string; email: string; phone: string | null; preferredLanguage: string }
}

export interface PasswordChange {
  currentPassword: string
  password: string
  password_confirmation: string
}

export const profileApi = {
  get: async () => (await api.get<ApiEnvelope<ProviderProfile>>('/profile')).data,

  changePassword: async (payload: PasswordChange) =>
    (await api.put<ApiEnvelope<{ message: string }>>('/profile/password', payload)).data.message,
}
