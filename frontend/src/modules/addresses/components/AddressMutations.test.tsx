import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { EntityAddressesPanel } from './EntityAddressesPanel'
import {
  addressesHandler,
  contactsHandler,
  CUSTOMER_ID,
  makeAddress,
  makeLink,
} from './addressTestData'
import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

const address = makeAddress('a1', { links: [makeLink('l1', 'delivery')] })

function baseHandlers(rows: unknown[] = [address]) {
  return [addressesHandler(rows), contactsHandler()]
}

const editor = withPermissions([
  'addresses.view',
  'addresses.create',
  'addresses.update',
  'addresses.delete',
  'contacts.create',
  'contacts.update',
])

function panel(membership = editor) {
  renderWithProviders(<EntityAddressesPanel entityType="customer" entityId={CUSTOMER_ID} />, {
    membership,
  })
}

describe('actions sur les adresses', () => {
  it('propose l’ajout avec addresses.create', async () => {
    server.use(...baseHandlers([]))
    panel()

    expect(await screen.findByRole('button', { name: 'Ajouter une adresse' })).toBeInTheDocument()
  })

  it('masque l’ajout sans la permission', async () => {
    server.use(...baseHandlers([]))
    panel(withPermissions(['addresses.view']))

    await screen.findByText('Aucune adresse')
    expect(screen.queryByRole('button', { name: 'Ajouter une adresse' })).not.toBeInTheDocument()
  })

  /**
   * `POST /addresses` crée l'adresse **et** sa liaison en un appel : le type et
   * l'identifiant de l'entité partent avec les champs postaux.
   */
  it('envoie le type et l’entité à la création', async () => {
    const bodies: Record<string, unknown>[] = []
    server.use(
      ...baseHandlers([]),
      http.post(`${API}/addresses`, async ({ request }) => {
        bodies.push((await request.json()) as Record<string, unknown>)

        return HttpResponse.json({ data: address, meta: [] }, { status: 201 })
      }),
    )
    panel()

    await userEvent.click(await screen.findByRole('button', { name: 'Ajouter une adresse' }))
    await userEvent.type(screen.getByLabelText(/^Adresse \*/), '12 rue de la Gare')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => {
      expect(bodies).toHaveLength(1)
    })
    expect(bodies[0]).toMatchObject({
      addressLine1: '12 rue de la Gare',
      addressType: 'delivery',
      entityType: 'customer',
      entityId: CUSTOMER_ID,
    })
  })

  it('refuse une adresse sans ligne postale', async () => {
    server.use(...baseHandlers([]))
    panel()

    await userEvent.click(await screen.findByRole('button', { name: 'Ajouter une adresse' }))
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(await screen.findByText('Ce champ est obligatoire.')).toBeInTheDocument()
  })

  it('demande confirmation avant de supprimer', async () => {
    server.use(...baseHandlers())
    panel()

    await screen.findByText('Entrepôt Nord')
    await userEvent.click(screen.getByRole('button', { name: 'Supprimer' }))

    const dialog = await screen.findByRole('dialog')
    expect(dialog).toHaveTextContent('Confirmer la suppression')
  })

  /**
   * Le contact est créé puis rattaché : l'API n'expose pas de création directe
   * sur une adresse. Les deux appels doivent partir, dans cet ordre.
   */
  it('crée le contact puis le rattache à l’adresse', async () => {
    const calls: string[] = []
    server.use(
      ...baseHandlers(),
      http.post(`${API}/contacts`, () => {
        calls.push('contacts')

        return HttpResponse.json({ data: { id: 'ct1' }, meta: [] }, { status: 201 })
      }),
      http.post(`${API}/addresses/:id/contacts`, () => {
        calls.push('attach')

        return HttpResponse.json({ data: { id: 'link1' }, meta: [] }, { status: 201 })
      }),
    )
    panel()

    await userEvent.click(await screen.findByRole('button', { name: 'Ajouter un contact' }))
    await userEvent.type(screen.getByLabelText(/^Prénom \*/), 'Sara')
    await userEvent.type(screen.getByLabelText(/^Nom \*/), 'Amrani')
    await userEvent.click(screen.getByRole('button', { name: 'Ajouter' }))

    await waitFor(() => {
      expect(calls).toEqual(['contacts', 'attach'])
    })
  })

  it('masque l’ajout de contact sans contacts.create', async () => {
    server.use(...baseHandlers())
    panel(withPermissions(['addresses.view']))

    await screen.findByText('Entrepôt Nord')
    expect(screen.queryByRole('button', { name: 'Ajouter un contact' })).not.toBeInTheDocument()
  })

  it('reporte une erreur du serveur dans le formulaire', async () => {
    server.use(
      ...baseHandlers([]),
      http.post(`${API}/addresses`, () =>
        HttpResponse.json(
          { message: 'Les données fournies sont invalides.', errors: { addressLine1: ['Adresse trop courte.'] } },
          { status: 422 },
        ),
      ),
    )
    panel()

    await userEvent.click(await screen.findByRole('button', { name: 'Ajouter une adresse' }))
    await userEvent.type(screen.getByLabelText(/^Adresse \*/), '12')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(await screen.findByText('Adresse trop courte.')).toBeInTheDocument()
  })
})
