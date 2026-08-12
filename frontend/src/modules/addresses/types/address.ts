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
  /**
   * Liaisons vers les entités qui utilisent cette adresse.
   *
   * Présentes uniquement lorsque la liste a été filtrée par entité. C'est la
   * liaison qui porte le type — livraison, facturation — et non l'adresse : la
   * même adresse peut servir de lieu de livraison à un client et d'adresse de
   * facturation à un autre.
   */
  links?: EntityAddressLink[]
  createdAt: string
  updatedAt: string
}

export interface EntityAddressLink {
  id: string
  entityType: string
  entityId: string
  addressType: string | null
  isDefault: boolean
}

/**
 * Entites auxquelles une adresse peut etre rattachee.
 *
 * La liste reprend `StoreAddressRequest::allowedEntityTypes()` : envoyer un
 * autre alias ferait echouer la validation cote API.
 */
export type AddressEntityType = 'organization' | 'customer' | 'customer_site' | 'agency' | 'depot'

/**
 * Rôles proposés pour une liaison adresse ↔ entité.
 *
 * `EntityAddress.addressType` est une chaîne libre côté base — le diagramme le
 * prévoit ainsi. Les valeurs proposées reprennent celles de l'énumération
 * `ContactRole`, seul vocabulaire existant pour cette distinction dans le
 * domaine ; en inventer d'autres produirait des données incohérentes d'un
 * écran à l'autre.
 */
export const ADDRESS_TYPES = ['delivery', 'billing', 'load', 'operations', 'other'] as const

export type AddressType = (typeof ADDRESS_TYPES)[number]

/** Rôles d'un contact, relevés sur l'énumération `ContactRole`. */
export const CONTACT_ROLES = [
  'load',
  'delivery',
  'billing',
  'operations',
  'emergency',
  'other',
] as const

/** Type de la liaison pour une entité donnée, ou `null` s'il n'est pas précisé. */
export function linkTypeFor(address: Address, entityId: string): string | null {
  return address.links?.find((link) => link.entityId === entityId)?.addressType ?? null
}
