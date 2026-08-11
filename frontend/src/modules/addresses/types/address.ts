/** Adresse — champs releves sur `AddressResource`. */
export interface Address {
  id: string
  code: string | null
  name: string | null
  addressLine1: string
  addressLine2: string | null
  addressLine3: string | null
  floor: string | null
  addressNumber: string | null
  route: string | null
  sublocality: string | null
  postalCode: string | null
  city: string | null
  town: string | null
  country: string | null
  latitude: string | number | null
  longitude: string | number | null
  instructions: string | null
  timeWindowFrom: string | null
  timeWindowTo: string | null
  isDefault: boolean
  status: string
  createdAt: string
  updatedAt: string
}

/**
 * Entites auxquelles une adresse peut etre rattachee.
 *
 * La liste reprend `StoreAddressRequest::allowedEntityTypes()` : envoyer un
 * autre alias ferait echouer la validation cote API.
 */
export type AddressEntityType = 'organization' | 'customer' | 'customer_site' | 'agency' | 'depot'
