import { api } from '@/shared/api/client'
import type { ApiEnvelope, CustomerContext } from '@/shared/api/types'

export interface LoginPayload {
  email: string
  password: string
  customerId?: string
}

export interface CustomerChoice {
  id: string
  name: string
}

export interface InvitationPreview {
  email: string
  customerName: string
  carrierName: string | null
  expiresAt: string
  requiresPassword: boolean
  firstName: string
  lastName: string
}

export interface ActivationPayload {
  token: string
  firstName?: string
  lastName?: string
  password?: string
  password_confirmation?: string
}

export interface ResetPasswordPayload {
  token: string
  email: string
  password: string
  password_confirmation: string
}

type Message = ApiEnvelope<{ message: string }>

export const authApi = {
  login: async (payload: LoginPayload) =>
    (await api.post<ApiEnvelope<{ token: string; context: CustomerContext }>>('/auth/login', payload)).data,

  logout: () => api.post<void>('/auth/logout'),

  context: async () => (await api.get<ApiEnvelope<CustomerContext>>('/context')).data,

  forgotPassword: async (email: string) =>
    (await api.post<Message>('/auth/forgot-password', { email })).data.message,

  resetPassword: async (payload: ResetPasswordPayload) =>
    (await api.post<Message>('/auth/reset-password', payload)).data.message,

  invitation: async (token: string) =>
    (await api.get<ApiEnvelope<InvitationPreview>>('/auth/invitation', { query: { token } })).data,

  activate: async (payload: ActivationPayload) =>
    (await api.post<Message>('/auth/activate', payload)).data.message,
}
