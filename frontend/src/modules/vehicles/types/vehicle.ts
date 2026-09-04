/**
 * Véhicule — `VehicleListResource` / `VehicleDetailResource`.
 *
 * `vehicleTypeId` désigne une **valeur du référentiel `vehicle`**, dans
 * `type_items` : les trois référentiels de type ont été réunis le 26 août 2026.
 * La règle serveur `IsTypeItemOf('vehicle')` vérifie la provenance.
 */
export interface Vehicle {
  id: string
  organizationId: string
  providerId: string | null
  vehicleTypeId: string
  code: string
  registrationNumber: string
  payloadCapacity: number | string
  volumeCapacity: number | string
  palletCapacity: number
  status: string
  providerName?: string
  vehicleTypeName?: string
}

export interface VehiclePayload {
  providerId?: string | null
  vehicleTypeId: string
  code: string
  registrationNumber: string
  payloadCapacity: number
  volumeCapacity: number
  palletCapacity: number
  status: string
}

export interface VehicleFilters {
  page: number
  perPage: number
  search?: string
  status?: string
  providerId?: string
  vehicleTypeId?: string
  payloadCapacityMin?: number
  volumeCapacityMin?: number
  palletCapacityMin?: number
  sort?: string
  direction?: 'asc' | 'desc'
}
