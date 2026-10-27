import type { ResourceActions } from '@/modules/drivers/types'

export interface Vehicle {
  id: string
  code: string
  registrationNumber: string
  vehicleType: { id: string; name: string } | null
  payloadCapacity: string
  volumeCapacity: string
  palletCapacity: number
  status: string
  statusLabel: string | null
  actions: ResourceActions
}

export interface VehiclePayload {
  code: string
  registrationNumber: string
  vehicleTypeId: string
  payloadCapacity: number
  volumeCapacity: number
  palletCapacity: number
}

export interface VehicleType {
  id: string
  code: string
  name: string
}
