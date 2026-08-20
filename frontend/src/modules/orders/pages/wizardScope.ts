import { HttpResponse, http } from 'msw'

import { paginated } from '@/test/fixtures'
import { API, server } from '@/test/server'

export const CUSTOMER_ID = '01JQZ000000000000000CUST'
export const AGENCY_ID = '01JQZ0000000000000000AGY1'
export const SERVICE_ID = '01JQZ00000000000000SERV1'
export const ADDRESS_ID = '01JQZ00000000000000ADDR1'
export const CATALOG_ID = '01JQZ0000000000000CATA01'
export const ITEM_ID = '01JQZ00000000000000ITEM1'

const customer = (catalogEnabled: boolean) => ({
  id: CUSTOMER_ID,
  organizationId: '01JQZ0000000000000000ORG1',
  code: 'CLI001',
  name: 'Client Alpha',
  legalName: null,
  email: null,
  phone: null,
  paymentMode: null,
  communicationMode: null,
  catalogEnabled,
  stockEnabled: false,
  packageEnabled: false,
  appointmentEnabled: false,
  trackingEnabled: false,
  status: 'active',
  createdAt: '2026-02-01T10:00:00.000000Z',
  updatedAt: '2026-02-01T10:00:00.000000Z',
})

const service = {
  id: SERVICE_ID,
  organizationId: '01JQZ0000000000000000ORG1',
  code: 'LIV',
  name: 'Livraison standard',
  unit: 'colis',
  defaultDurationMinutes: 30,
  billableToCustomer: true,
  payableToProvider: true,
  requiresAddress: true,
  requiresContact: false,
  status: 'active',
}

const address = {
  id: ADDRESS_ID,
  code: null,
  name: 'Entrepôt Casablanca',
  addressLine1: '12 rue des Docks',
  addressLine2: null,
  addressLine3: null,
  floor: null,
  addressNumber: null,
  route: null,
  sublocality: null,
  postalCode: '20000',
  city: 'Casablanca',
  town: null,
  country: 'MA',
  latitude: null,
  longitude: null,
  instructions: null,
  timeWindowFrom: null,
  timeWindowTo: null,
  isDefault: true,
  status: 'active',
  createdAt: '2026-02-01T10:00:00.000000Z',
  updatedAt: '2026-02-01T10:00:00.000000Z',
}

export const catalogItem = {
  id: ITEM_ID,
  catalogId: CATALOG_ID,
  articleCode: 'ART-9',
  barcode: '3760000000009',
  name: 'Carton renforcé',
  description: null,
  weight: 2.5,
  volume: null,
  length: null,
  width: null,
  height: null,
  status: 'active',
  createdAt: '2026-02-01T10:00:00.000000Z',
  updatedAt: '2026-02-01T10:00:00.000000Z',
}

/**
 * Référentiels interrogés par le formulaire de commande.
 *
 * `onUnhandledRequest: 'error'` impose de tous les déclarer : une route oubliée
 * doit faire échouer le test, pas passer inaperçue.
 */
export function serveWizardScope({ catalogEnabled = true } = {}) {
  server.use(
    http.get(`${API}/customers`, () => HttpResponse.json(paginated([customer(catalogEnabled)]))),
    http.get(`${API}/customers/${CUSTOMER_ID}`, () =>
      HttpResponse.json({ data: customer(catalogEnabled), meta: [] }),
    ),
    http.get(`${API}/agencies`, () =>
      HttpResponse.json(
        paginated([
          {
            id: AGENCY_ID,
            organizationId: '01JQZ0000000000000000ORG1',
            code: 'AG01',
            name: 'Agence Nord',
            status: 'active',
            createdAt: '2026-02-01T10:00:00.000000Z',
            updatedAt: '2026-02-01T10:00:00.000000Z',
          },
        ]),
      ),
    ),
    http.get(`${API}/agencies/${AGENCY_ID}/depots`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/services`, () => HttpResponse.json(paginated([service]))),
    http.get(`${API}/customers/${CUSTOMER_ID}/sites`, () => HttpResponse.json(paginated([]))),
    // Le carnet du client, et la liste hors carnet portee par l'organisation :
    // deux entites distinctes pour `GET /addresses?entityType=`.
    http.get(`${API}/addresses`, ({ request }) => {
      const entityType = new URL(request.url).searchParams.get('entityType')

      return HttpResponse.json(paginated(entityType === 'organization' ? [] : [address]))
    }),
    http.get(`${API}/addresses/:addressId/contacts`, () =>
      HttpResponse.json({ data: [], meta: [] }),
    ),
    http.get(`${API}/package-types`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/package-grouping-types`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/customers/${CUSTOMER_ID}/catalogs`, () =>
      HttpResponse.json(
        paginated([
          {
            id: CATALOG_ID,
            customerId: CUSTOMER_ID,
            code: 'CAT-2026',
            name: 'Catalogue général',
            description: null,
            status: 'active',
            itemCount: 1,
            createdAt: '2026-02-01T10:00:00.000000Z',
            updatedAt: '2026-02-01T10:00:00.000000Z',
          },
        ]),
      ),
    ),
    http.get(`${API}/customers/${CUSTOMER_ID}/catalogs/${CATALOG_ID}/items`, () =>
      HttpResponse.json(paginated([catalogItem])),
    ),
  )
}
