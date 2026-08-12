import { HttpResponse, http } from 'msw'

import { paginated } from '@/test/fixtures'
import { API } from '@/test/server'

export const CUSTOMER_ID = '01JQZ000000000000000CUST'

/**
 * Adresse telle que la renvoie `AddressResource`.
 *
 * Nommée à dessein : l'en-tête de la carte affiche le nom et le corps l'adresse
 * postale. Sans nom, les deux affichent la même ligne et les assertions
 * deviennent ambiguës.
 */
export function makeAddress(id: string, overrides: Record<string, unknown> = {}) {
  return {
    id,
    code: null,
    name: 'Entrepôt Nord',
    addressLine1: '12 rue de la Gare',
    addressLine2: null,
    addressLine3: null,
    floor: null,
    addressNumber: null,
    route: null,
    sublocality: null,
    postalCode: '75001',
    city: 'Paris',
    town: null,
    country: 'FR',
    latitude: null,
    longitude: null,
    instructions: null,
    timeWindowFrom: null,
    timeWindowTo: null,
    isDefault: false,
    status: 'active',
    createdAt: '2026-01-01T00:00:00.000000Z',
    updatedAt: '2026-01-01T00:00:00.000000Z',
    ...overrides,
  }
}

/** Liaison entité ↔ adresse : c'est elle qui porte le type. */
export function makeLink(id: string, addressType: string, isDefault = false) {
  return { id, entityType: 'customer', entityId: CUSTOMER_ID, addressType, isDefault }
}

/** Capture les paramètres envoyés : le filtrage est serveur, pas local. */
export function addressesHandler(rows: unknown[], queries: URLSearchParams[] = []) {
  return http.get(`${API}/addresses`, ({ request }) => {
    queries.push(new URL(request.url).searchParams)

    return HttpResponse.json(paginated(rows))
  })
}

export function contactsHandler(rows: unknown[] = []) {
  return http.get(`${API}/addresses/:id/contacts`, () => HttpResponse.json({ data: rows, meta: [] }))
}

export function makeAddressContact(overrides: Record<string, unknown> = {}) {
  return {
    id: 'c1',
    addressId: 'a1',
    contactId: 'ct1',
    contactRole: 'delivery',
    isPrimary: true,
    contact: {
      id: 'ct1',
      firstName: 'Sara',
      lastName: 'Amrani',
      phone: '+212600000000',
      mobile: null,
      email: 'sara@example.test',
    },
    ...overrides,
  }
}
