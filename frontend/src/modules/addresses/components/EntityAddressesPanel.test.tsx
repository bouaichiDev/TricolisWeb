import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { EntityAddressesPanel } from './EntityAddressesPanel'
import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

const CUSTOMER_ID = '01JQZ000000000000000CUST'

function address(id: string, overrides: Record<string, unknown> = {}) {
  return {
    id,
    code: null,
    // Nommée : l'en-tête affiche le nom, le corps l'adresse postale. Sans nom,
    // les deux affichent la même ligne et l'assertion devient ambiguë.
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

function link(id: string, addressType: string, isDefault = false) {
  return { id, entityType: 'customer', entityId: CUSTOMER_ID, addressType, isDefault }
}

/** Capture les paramètres envoyés : le filtrage est serveur, pas local. */
function addressesHandler(rows: unknown[], queries: URLSearchParams[] = []) {
  return http.get(`${API}/addresses`, ({ request }) => {
    queries.push(new URL(request.url).searchParams)

    return HttpResponse.json(paginated(rows))
  })
}

function contactsHandler(rows: unknown[] = []) {
  return http.get(`${API}/addresses/:id/contacts`, () => HttpResponse.json({ data: rows, meta: [] }))
}

const viewer = withPermissions(['addresses.view'])

/**
 * Un client porte plusieurs adresses — livraison, facturation — et chacune
 * porte ses propres contacts. Le type vient de la **liaison**, pas de
 * l'adresse : la même adresse peut servir de lieu de livraison à l'un et
 * d'adresse de facturation à l'autre.
 */
describe('EntityAddressesPanel', () => {
  it('demande les adresses de la seule entité consultée', async () => {
    const queries: URLSearchParams[] = []
    server.use(addressesHandler([address('a1')], queries), contactsHandler())

    renderWithProviders(<EntityAddressesPanel entityType="customer" entityId={CUSTOMER_ID} />, {
      membership: viewer,
    })

    await screen.findByText('Entrepôt Nord')

    expect(queries[0].get('entityType')).toBe('customer')
    expect(queries[0].get('entityId')).toBe(CUSTOMER_ID)
  })

  it('affiche le type porté par la liaison', async () => {
    server.use(
      addressesHandler([
        address('a1', { links: [link('l1', 'delivery')] }),
        address('a2', { name: 'Siège', links: [link('l2', 'billing', true)] }),
      ]),
      contactsHandler(),
    )

    renderWithProviders(<EntityAddressesPanel entityType="customer" entityId={CUSTOMER_ID} />, {
      membership: viewer,
    })

    expect(await screen.findByText('Livraison')).toBeInTheDocument()
    expect(screen.getByText('Facturation')).toBeInTheDocument()
    expect(screen.getByText('Par défaut')).toBeInTheDocument()
  })

  /**
   * Une même adresse peut porter deux liaisons vers le même client, avec des
   * types différents. Chacune donne sa propre carte : n'en montrer qu'une
   * ferait disparaître l'un des deux rôles.
   */
  it('rend une carte par liaison quand une adresse en porte plusieurs', async () => {
    server.use(
      addressesHandler([
        address('a1', { links: [link('l1', 'delivery'), link('l2', 'billing')] }),
      ]),
      contactsHandler(),
    )

    renderWithProviders(<EntityAddressesPanel entityType="customer" entityId={CUSTOMER_ID} />, {
      membership: viewer,
    })

    await screen.findByText('Livraison')
    expect(screen.getByText('Facturation')).toBeInTheDocument()
    expect(screen.getAllByText('Entrepôt Nord')).toHaveLength(2)
  })

  it('écarte une liaison qui vise une autre entité', async () => {
    server.use(
      addressesHandler([
        address('a1', {
          links: [{ id: 'l9', entityType: 'customer', entityId: 'AUTRE', addressType: 'billing', isDefault: false }],
        }),
      ]),
      contactsHandler(),
    )

    renderWithProviders(<EntityAddressesPanel entityType="customer" entityId={CUSTOMER_ID} />, {
      membership: viewer,
    })

    await screen.findByText('Entrepôt Nord')
    expect(screen.queryByText('Facturation')).not.toBeInTheDocument()
  })

  it('affiche les contacts rattachés à l’adresse', async () => {
    server.use(
      addressesHandler([address('a1', { links: [link('l1', 'delivery')] })]),
      contactsHandler([
        {
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
        },
      ]),
    )

    renderWithProviders(<EntityAddressesPanel entityType="customer" entityId={CUSTOMER_ID} />, {
      membership: viewer,
    })

    expect(await screen.findByText('Sara Amrani')).toBeInTheDocument()
    expect(screen.getByText('sara@example.test')).toBeInTheDocument()
    expect(screen.getByText('Principal')).toBeInTheDocument()
  })

  it('dit qu’une adresse n’a aucun contact plutôt que de ne rien montrer', async () => {
    server.use(
      addressesHandler([address('a1', { links: [link('l1', 'delivery')] })]),
      contactsHandler([]),
    )

    renderWithProviders(<EntityAddressesPanel entityType="customer" entityId={CUSTOMER_ID} />, {
      membership: viewer,
    })

    expect(await screen.findByText('Aucun contact rattaché à cette adresse.')).toBeInTheDocument()
  })

  it('masque le détachement sans addresses.update', async () => {
    server.use(
      addressesHandler([address('a1', { links: [link('l1', 'delivery')] })]),
      contactsHandler([
        {
          id: 'c1',
          addressId: 'a1',
          contactId: 'ct1',
          contactRole: null,
          isPrimary: false,
          contact: {
            id: 'ct1',
            firstName: 'Sara',
            lastName: 'Amrani',
            phone: null,
            mobile: null,
            email: null,
          },
        },
      ]),
    )

    renderWithProviders(<EntityAddressesPanel entityType="customer" entityId={CUSTOMER_ID} />, {
      membership: viewer,
    })

    await screen.findByText('Sara Amrani')
    expect(screen.queryByRole('button', { name: 'Détacher le contact' })).not.toBeInTheDocument()
  })

  it('annonce l’absence d’adresse', async () => {
    server.use(addressesHandler([]))

    renderWithProviders(
      <EntityAddressesPanel
        entityType="customer"
        entityId={CUSTOMER_ID}
        emptyMessage="Aucune adresse pour ce client"
      />,
      { membership: viewer },
    )

    expect(await screen.findByText('Aucune adresse pour ce client')).toBeInTheDocument()
  })

  it('remonte l’échec de chargement', async () => {
    server.use(
      http.get(`${API}/addresses`, () =>
        HttpResponse.json({ message: 'Service indisponible.' }, { status: 500 }),
      ),
    )

    renderWithProviders(<EntityAddressesPanel entityType="customer" entityId={CUSTOMER_ID} />, {
      membership: viewer,
    })

    expect(await screen.findByText('Service indisponible.')).toBeInTheDocument()
  })

  it('n’affiche pas les contacts quand l’écran ne les demande pas', async () => {
    server.use(addressesHandler([address('a1', { links: [link('l1', 'delivery')] })]))

    renderWithProviders(
      <EntityAddressesPanel entityType="customer" entityId={CUSTOMER_ID} hideContacts />,
      { membership: viewer },
    )

    await screen.findByText('Entrepôt Nord')
    expect(screen.queryByText('Aucun contact rattaché à cette adresse.')).not.toBeInTheDocument()
  })
})
