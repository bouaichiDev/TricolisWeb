/** Agence — champs releves sur `AgencyResource`. */
export interface Agency {
  id: string
  organizationId: string
  code: string
  name: string
  shortName: string | null
  email: string | null
  phone: string | null
  color: string | null
  loadingPoint: string | null
  status: string
  createdAt: string
  updatedAt: string
}

export interface AgencyFilters {
  page?: number
  perPage?: number
  search?: string
  status?: string
  sort?: string
  direction?: 'asc' | 'desc'
}
