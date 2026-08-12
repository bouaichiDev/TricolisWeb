import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { EntityAddressesPanel } from './EntityAddressesPanel'
import {
  addressesHandler,
  contactsHandler,
  CUSTOMER_ID,
  makeAddress,
  makeAddressContact,
  makeLink,
} from './addressTestData'
import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

const viewer = withPermissions(['addresses.view'])

function panel() {
  renderWithProviders(<EntityAddressesPanel entityType="customer" entityId={CUSTOMER_ID} />, {
    membership: viewer,
  })
}

/**
 * Un client porte plusieurs adresses — livraison, facturation — et chacune
 * porte ses propres contacts. Le type vient de la **liaison**, pas de
 * l'adresse : la même adresse peut servir de lieu de livraison à l'un et
 * d'adresse de facturation à l'autre.
 */
describe('EntityAddressesPanel', () => {
  it('demande les adresses de la seule entité consultée', async () => {
    const queries: URLSearchParams[] = []
    server.use(addressesHandler([makeAddress('a1')], queries), contactsHandler())
    panel()

    await screen.findByText('Entrepôt Nord')

    expect(queries[0].get('entityType')).toBe('customer')
    expect(queries[0].get('entityId')).toBe(CUSTOMER_ID)
  })

  it('affiche le type porté par la liaison', async () => {
    server.use(
      addressesHandler([
        makeAddress('a1', { links: [makeLink('l1', 'delivery')] }),
        makeAddress('a2', { name: 'Siège', links: [makeLink('l2', 'billing', true)] }),
      ]),
      contactsHandler(),
    )
    panel()

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
        makeAddress('a1', { links: [makeLink('l1', 'delivery'), makeLink('l2', 'billing')] }),
      ]),
      contactsHandler(),
    )
    panel()

    await screen.findByText('Livraison')
    expect(screen.getByText('Facturation')).toBeInTheDocument()
    expect(screen.getAllByText('Entrepôt Nord')).toHaveLength(2)
  })

  it('écarte une liaison qui vise une autre entité', async () => {
    server.use(
      addressesHandler([
        makeAddress('a1', {
          links: [
            { id: 'l9', entityType: 'customer', entityId: 'AUTRE', addressType: 'billing', isDefault: false },
          ],
        }),
      ]),
      contactsHandler(),
    )
    panel()

    await screen.findByText('Entrepôt Nord')
    expect(screen.queryByText('Facturation')).not.toBeInTheDocument()
  })

  it('affiche les contacts rattachés à l’adresse', async () => {
    server.use(
      addressesHandler([makeAddress('a1', { links: [makeLink('l1', 'delivery')] })]),
      contactsHandler([makeAddressContact()]),
    )
    panel()

    expect(await screen.findByText('Sara Amrani')).toBeInTheDocument()
    expect(screen.getByText('sara@example.test')).toBeInTheDocument()
    expect(screen.getByText('Principal')).toBeInTheDocument()
  })

  it('dit qu’une adresse n’a aucun contact plutôt que de ne rien montrer', async () => {
    server.use(
      addressesHandler([makeAddress('a1', { links: [makeLink('l1', 'delivery')] })]),
      contactsHandler([]),
    )
    panel()

    expect(await screen.findByText('Aucun contact rattaché à cette adresse.')).toBeInTheDocument()
  })

  it('masque le détachement sans addresses.update', async () => {
    server.use(
      addressesHandler([makeAddress('a1', { links: [makeLink('l1', 'delivery')] })]),
      contactsHandler([makeAddressContact()]),
    )
    panel()

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
    panel()

    expect(await screen.findByText('Service indisponible.')).toBeInTheDocument()
  })

  it('n’affiche pas les contacts quand l’écran ne les demande pas', async () => {
    server.use(addressesHandler([makeAddress('a1', { links: [makeLink('l1', 'delivery')] })]))

    renderWithProviders(
      <EntityAddressesPanel entityType="customer" entityId={CUSTOMER_ID} hideContacts />,
      { membership: viewer },
    )

    await screen.findByText('Entrepôt Nord')
    expect(screen.queryByText('Aucun contact rattaché à cette adresse.')).not.toBeInTheDocument()
  })
})
