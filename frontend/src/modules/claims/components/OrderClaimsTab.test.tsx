import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { OrderDetailPage } from '@/modules/orders/pages/OrderDetailPage'
import { makeOrderDetail, ORDER_ID, PACKAGE_TREE } from '@/modules/orders/pages/orderDetailFixtures'

const CLAIM_ID = '01JQZ0000000000000CLAIM1'
const CUSTOMER_ID = '01JQZ000000000000000CUST'

const claim = (overrides: Record<string, unknown> = {}) => ({
  id: CLAIM_ID,
  organizationId: '01JQZ0000000000000000ORG1',
  customerId: CUSTOMER_ID,
  orderId: ORDER_ID,
  orderServiceId: null,
  tourId: null,
  title: 'Canapé livré rayé',
  claimType: 'casse',
  result: null,
  cost: 250,
  status: 'open',
  responsibleUserId: null,
  createdAt: '2026-08-06T09:00:00+00:00',
  closedAt: null,
  customerName: 'Client Alpha',
  ...overrides,
})

const status = (code: string, label: string, position: number) => ({
  id: `01JQZ000000000000CSTA${position}`,
  source: 'claim',
  status: position,
  code,
  label,
  icon: null,
  active: true,
  isToSend: false,
  allowsContentChanges: true,
  requiresReason: false,
  position: position * 10,
  createdAt: '2026-08-01T09:00:00.000000Z',
  updatedAt: '2026-08-01T09:00:00.000000Z',
})

function renderDetail(permissions: string[], statuses = [status('open', 'Ouverte', 1)]) {
  const calls: URL[] = []

  server.use(
    http.get(`${API}/orders/${ORDER_ID}`, () =>
      HttpResponse.json({ data: makeOrderDetail(), meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/packages/tree`, () =>
      HttpResponse.json({ data: PACKAGE_TREE, meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/history`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/orders/${ORDER_ID}/services/:serviceId/packages`, () =>
      HttpResponse.json({ data: [], meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/documents`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/audit-logs`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/statuses`, () => HttpResponse.json(paginated(statuses))),
    http.get(`${API}/organization-users`, () => HttpResponse.json(paginated([]))),
    // `ClaimListResource` n'expose ni description, ni cause, ni traitement :
    // seul le detail les porte.
    http.get(`${API}/claims/${CLAIM_ID}`, () =>
      HttpResponse.json({
        data: {
          ...claim(),
          description: 'Le revêtement est griffé sur tout un accoudoir.',
          cause: 'Manutention',
          decision: 'Remplacement accepté',
          followUp: 'Nouveau canapé commandé',
          orderServiceId: null,
        },
        meta: [],
      }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/claims`, ({ request }) => {
      calls.push(new URL(request.url))

      return HttpResponse.json(paginated([claim()]))
    }),
  )

  renderWithProviders(<OrderDetailPage />, {
    membership: withPermissions(permissions),
    route: `/orders/${ORDER_ID}`,
    routePath: '/orders/:id',
  })

  return calls
}

const openClaims = () =>
  screen.findByRole('tab', { name: /^Réclamations/ }).then((tab) => userEvent.click(tab))

describe('réclamations d’une commande', () => {
  it('liste les réclamations de la commande', async () => {
    renderDetail(['orders.view', 'claims.view'])

    await openClaims()

    expect(await screen.findByText('Canapé livré rayé')).toBeInTheDocument()
    expect(screen.getByText('casse')).toBeInTheDocument()
  })

  /**
   * Le client vient de la commande : la création passe par
   * `POST /customers/{customer}/claims`, où il est dans l'URL. Aucun sélecteur
   * de client n'existe — c'est la façon la plus sûre d'interdire d'en choisir
   * un autre.
   */
  it('crée sans jamais demander le client', async () => {
    let body: unknown = null
    renderDetail(['orders.view', 'claims.view', 'claims.create'])

    server.use(
      http.post(`${API}/customers/${CUSTOMER_ID}/claims`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: claim(), meta: [] }, { status: 201 })
      }),
    )

    await openClaims()
    await userEvent.click(await screen.findByRole('button', { name: /Nouvelle réclamation/ }))

    const dialog = within(await screen.findByRole('dialog'))
    expect(dialog.queryByLabelText(/^Client/)).not.toBeInTheDocument()

    await userEvent.type(dialog.getByLabelText(/^Titre/), 'Retard de livraison')
    await userEvent.type(dialog.getByLabelText(/^Type/), 'retard')
    await userEvent.click(dialog.getByLabelText(/^Statut/))
    await userEvent.click(await screen.findByRole('option', { name: /Ouverte/ }))
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({
      title: 'Retard de livraison',
      claimType: 'retard',
      status: 'open',
      orderId: ORDER_ID,
    })
  })

  /** Le traitement n'est pas accepté à la création : il n'est pas montré. */
  it('cache le traitement à la création, le montre en modification', async () => {
    renderDetail(['orders.view', 'claims.view', 'claims.create', 'claims.update'])

    await openClaims()
    await userEvent.click(await screen.findByRole('button', { name: /Nouvelle réclamation/ }))

    let dialog = within(await screen.findByRole('dialog'))
    expect(dialog.queryByLabelText(/^Décision/)).not.toBeInTheDocument()
    await userEvent.click(dialog.getByRole('button', { name: 'Annuler' }))

    await userEvent.click(await screen.findByRole('button', { name: 'Modifier' }))

    dialog = within(await screen.findByRole('dialog'))
    expect(await dialog.findByLabelText(/^Décision/)).toBeInTheDocument()
    expect(dialog.getByLabelText(/^Coût/)).toHaveValue(250)
  })

  /**
   * La liste ne porte ni description, ni cause, ni traitement.
   *
   * Construire le formulaire depuis la ligne les affichait vides, et
   * enregistrer les effaçait — une perte silencieuse. Le détail est donc
   * rechargé avant d'ouvrir la saisie.
   */
  it('recharge le détail avant modification, sans effacer ce qui est absent de la liste', async () => {
    let body: unknown = null
    renderDetail(['orders.view', 'claims.view', 'claims.update'])

    server.use(
      http.patch(`${API}/claims/${CLAIM_ID}`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: claim(), meta: [] })
      }),
    )

    await openClaims()
    await userEvent.click(await screen.findByRole('button', { name: 'Modifier' }))

    const dialog = within(await screen.findByRole('dialog'))
    expect(await dialog.findByLabelText(/^Description/)).toHaveValue(
      'Le revêtement est griffé sur tout un accoudoir.',
    )

    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({
      description: 'Le revêtement est griffé sur tout un accoudoir.',
      cause: 'Manutention',
      decision: 'Remplacement accepté',
      followUp: 'Nouveau canapé commandé',
    })
  })

  /** Affecter dès la création : le serveur accepte `responsibleUserId`. */
  it('permet d’affecter la réclamation à un membre', async () => {
    renderDetail(['orders.view', 'claims.view', 'claims.create'])

    await openClaims()
    await userEvent.click(await screen.findByRole('button', { name: /Nouvelle réclamation/ }))

    const dialog = within(await screen.findByRole('dialog'))
    expect(dialog.getByLabelText(/^Responsable/)).toBeInTheDocument()
  })

  /** Le référentiel est vide pour `claim` : le dire, pas inventer une liste. */
  it('explique quand aucun statut n’est décrit', async () => {
    renderDetail(['orders.view', 'claims.view', 'claims.create'], [])

    await openClaims()
    await userEvent.click(await screen.findByRole('button', { name: /Nouvelle réclamation/ }))

    expect(
      await screen.findByText(/Aucun statut n’est décrit pour les réclamations/),
    ).toBeInTheDocument()
  })

  it('n’interroge les réclamations qu’une fois l’onglet ouvert', async () => {
    const calls = renderDetail(['orders.view', 'claims.view'])

    await screen.findByRole('tab', { name: /^Réclamations/ })
    expect(calls).toHaveLength(0)

    await openClaims()
    await waitFor(() => expect(calls.length).toBeGreaterThan(0))
  })

  it('masque création, modification et suppression sans les permissions', async () => {
    renderDetail(['orders.view', 'claims.view'])

    await openClaims()
    await screen.findByText('Canapé livré rayé')

    expect(screen.queryByRole('button', { name: /Nouvelle réclamation/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Modifier' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument()
  })
})
