import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { makeClaim, makeClaimDetail, makeDocument, makeOrderDetail } from '@/test/afterDeliveryFixtures'
import { makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'

const PERMISSIONS = ['customer.orders.view', 'customer.claims.view', 'customer.claims.create', 'customer.claims.update']
const page = (rows: unknown[]) => ({ data: rows, meta: { currentPage: 1, perPage: 20, total: rows.length, lastPage: 1 } })
const order = { id: '01ORDER', reference: 'IKEA-2026-000001', customerReference: 'IKEA-4587', externalReference: null, orderDate: null, firstRequestedDate: null, status: { code: 'planned', label: 'Planifiée', icon: null }, source: null, servicesCount: 1, packageCount: 0, weight: null, createdAt: null, actions: makeOrderDetail().actions }

describe('claims pages', () => {
  it('lists the claims of the company', async () => {
    server.use(http.get(`${API}/claims`, () => HttpResponse.json(page([makeClaim(), makeClaim({ id: '01C2', title: 'Retard', isClosed: true, closedAt: '2026-09-30T10:00:00+02:00' })]))))

    renderApp('/claims', makeContext(PERMISSIONS))

    expect(await screen.findByRole('link', { name: 'Carton écrasé' })).toHaveAttribute('href', '/claims/01CLAIM')
    expect(screen.getByRole('link', { name: 'Retard' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Nouvelle réclamation/ })).toBeInTheDocument()
  })

  it('says when there is no claim yet', async () => {
    server.use(http.get(`${API}/claims`, () => HttpResponse.json(page([]))))

    renderApp('/claims', makeContext(PERMISSIONS))

    expect(await screen.findByText('Aucune réclamation pour le moment.')).toBeInTheDocument()
  })

  it('creates a claim, the services following the chosen order', async () => {
    let body: unknown = null
    server.use(
      http.get(`${API}/orders`, () => HttpResponse.json(page([order]))),
      http.get(`${API}/orders/01ORDER`, () => HttpResponse.json({ data: makeOrderDetail(), meta: {} })),
      http.post(`${API}/claims`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: makeClaimDetail(), meta: {} }, { status: 201 })
      }),
      http.get(`${API}/claims/01CLAIM`, () => HttpResponse.json({ data: makeClaimDetail(), meta: {} })),
      http.get(`${API}/claims/01CLAIM/documents`, () => HttpResponse.json({ data: [], meta: {} })),
    )
    renderApp('/claims/new', makeContext(PERMISSIONS))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Envoyer la réclamation' }))
    expect(await screen.findByText('Donnez un titre à la réclamation.')).toBeInTheDocument()

    await user.selectOptions(await screen.findByLabelText('Commande'), '01ORDER')
    await user.selectOptions(await screen.findByLabelText('Service concerné (facultatif)'), await screen.findByRole('option', { name: 'S1 — Livraison' }))
    await user.type(screen.getByLabelText('Type de réclamation'), 'damage')
    await user.type(screen.getByLabelText('Titre'), 'Carton écrasé')
    await user.click(screen.getByRole('button', { name: 'Envoyer la réclamation' }))

    expect(await screen.findByRole('heading', { name: 'Carton écrasé' })).toBeInTheDocument()
    expect(body).toEqual({ orderId: '01ORDER', orderServiceId: '01SVC1', claimType: 'damage', title: 'Carton écrasé', description: null })
  })

  it('locks the order and the service when opened from a service', async () => {
    server.use(http.get(`${API}/orders`, () => HttpResponse.json(page([order]))), http.get(`${API}/orders/01ORDER`, () => HttpResponse.json({ data: makeOrderDetail(), meta: {} })))

    renderApp('/claims/new?orderId=01ORDER&orderServiceId=01SVC1', makeContext(PERMISSIONS))

    expect(await screen.findByText('S1 — Livraison')).toBeInTheDocument()
    expect(screen.queryByLabelText('Commande')).not.toBeInTheDocument()
  })

  it('shows a claim, its attachments, and lets the customer edit it', async () => {
    server.use(
      http.get(`${API}/claims/01CLAIM`, () => HttpResponse.json({ data: makeClaimDetail(), meta: {} })),
      http.get(`${API}/claims/01CLAIM/documents`, () => HttpResponse.json({ data: [makeDocument({ id: '01ATT', fileName: 'photo.pdf', isDeliveryNote: false, documentType: 'claim_evidence' })], meta: {} })),
    )

    renderApp('/claims/01CLAIM', makeContext(PERMISSIONS))

    expect(await screen.findByText('Deux cartons ouverts.')).toBeInTheDocument()
    expect(await screen.findByText('photo.pdf')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Joindre un fichier/ })).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /Modifier/ }))
    expect(within(await screen.findByRole('dialog')).getByLabelText('Titre')).toHaveValue('Carton écrasé')
  })

  it('offers neither edit nor attachment on a closed claim', async () => {
    server.use(
      http.get(`${API}/claims/01CLAIM`, () => HttpResponse.json({ data: makeClaimDetail({ isClosed: true, closedAt: '2026-09-30T10:00:00+02:00', actions: { canEdit: false, canAttach: false } }), meta: {} })),
      http.get(`${API}/claims/01CLAIM/documents`, () => HttpResponse.json({ data: [], meta: {} })),
    )

    renderApp('/claims/01CLAIM', makeContext(PERMISSIONS))

    expect(await screen.findByText('Aucune pièce jointe')).toBeInTheDocument()
    await waitFor(() => expect(screen.queryByRole('button', { name: /Modifier/ })).not.toBeInTheDocument())
    expect(screen.queryByRole('button', { name: /Joindre un fichier/ })).not.toBeInTheDocument()
  })

  it('presents the claim of another customer as not found, and closes the pages without permission', async () => {
    server.use(http.get(`${API}/claims/01QOQA`, () => HttpResponse.json({ message: 'Introuvable.' }, { status: 404 })))

    renderApp('/claims/01QOQA', makeContext(PERMISSIONS))
    expect(await screen.findByText('Réclamation introuvable')).toBeInTheDocument()
  })

  it('closes the claims pages without the permission', async () => {
    renderApp('/claims', makeContext(['customer.orders.view']))

    expect(await screen.findByRole('heading', { name: 'Accès refusé' })).toBeInTheDocument()
  })
})
