import { api } from '@/shared/api/client'
import type { ApiEnvelope, Paginated } from '@/shared/api/types'

export interface Communication {
  id: string
  channel: string
  channelLabel: string
  subject: string | null
  status: string
  statusLabel: string
  sentAt: string | null
  deliveredAt: string | null
  /** Au détail seulement, en texte brut. */
  body?: string
  attachments?: { fileName: string; mimeType: string | null }[]
}

/** Les communications reçues par le membre connecté : le serveur les filtre par son adresse. */
export const communicationsApi = {
  list: (page: number, perPage?: number) => api.get<Paginated<Communication>>('/communications', { query: { page, perPage } }),

  get: async (id: string) => (await api.get<ApiEnvelope<Communication>>(`/communications/${id}`)).data,
}
