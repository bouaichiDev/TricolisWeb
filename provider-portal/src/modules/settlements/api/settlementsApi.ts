import { api } from '@/shared/api/client'
import type { ApiEnvelope, Paginated } from '@/shared/api/types'

export interface SettlementLine {
  id: string
  serviceNumber: string | null
  description: string | null
  quantity: string
  unitCost: string
  totalCost: string
}

export interface Settlement {
  id: string
  settlementNumber: string
  periodFrom: string | null
  periodTo: string | null
  status: string
  statusLabel: string | null
  subtotal: string
  taxTotal: string
  total: string
  lines?: SettlementLine[]
}

export interface SettlementDocument {
  id: string
  fileName: string
  documentType: string
  mimeType: string | null
  size: number | null
  createdAt: string | null
}

/** Décomptes clôturés du fournisseur, en lecture seule : aucune écriture n'existe côté serveur. */
export const settlementsApi = {
  list: (page: number, perPage?: number) => api.get<Paginated<Settlement>>('/settlements', { query: { page, perPage } }),

  get: async (id: string) => (await api.get<ApiEnvelope<Settlement>>(`/settlements/${id}`)).data,

  documents: async (id: string) => (await api.get<ApiEnvelope<SettlementDocument[]>>(`/settlements/${id}/documents`)).data,

  file: (id: string, documentId: string) => api.blob(`/settlements/${id}/documents/${documentId}`),
}

/** Montant décimal du serveur, affiché sans recalcul — et sans devise : le décompte n'en porte pas. */
export const money = (value: string | null | undefined) =>
  value === null || value === undefined ? '—' : Number(value).toLocaleString('fr-CH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
