import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { makeClaim, makeDocument, makeOrderDetail, makeProof } from '@/test/afterDeliveryFixtures'
import { makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'

const ALL = ['customer.orders.view', 'customer.orders.documents.view', 'customer.orders.proofs_of_delivery.view', 'customer.claims.view', 'customer.claims.create']
const claims = (rows: unknown[]) => ({ data: rows, meta: { currentPage: 1, perPage: 50, total: rows.length, lastPage: 1 } })

beforeEach(() => {
  URL.createObjectURL = vi.fn(() => 'blob:signature')
  URL.revokeObjectURL = vi.fn()
  server.use(
    http.get(`${API}/orders/01ORDER`, () => HttpResponse.json({ data: makeOrderDetail(), meta: {} })),
    http.get(`${API}/documents/01SIG/download`, () => new HttpResponse('png', { headers: { 'Content-Type': 'image/png' } })),
  )
})

describe('order service page', () => {
  it('shows the delivery note, the proof with its signature, and the service claims', async () => {
    server.use(
      http.get(`${API}/orders/01ORDER/services/01SVC1/documents`, () => HttpResponse.json({ data: [makeDocument(), makeDocument({ id: '01D2', fileName: 'plan.pdf', isDeliveryNote: false, documentType: 'customer_upload' })], meta: {} })),
      http.get(`${API}/orders/01ORDER/services/01SVC1/proofs-of-delivery`, () => HttpResponse.json({ data: [makeProof()], meta: {} })),
      http.get(`${API}/claims`, () => HttpResponse.json(claims([makeClaim()]))),
    )

    renderApp('/orders/01ORDER/services/01SVC1', makeContext(ALL))

    const notes = await screen.findByRole('list', { name: 'Bons de livraison' })
    expect(within(notes).getByText(/BL-IKEA-2026-000001-S1/)).toBeInTheDocument()
    expect(within(notes).getByRole('button', { name: /Télécharger/ })).toBeInTheDocument()
    expect(await screen.findByText('plan.pdf')).toBeInTheDocument()

    const proof = await screen.findByTestId('proof-of-delivery')
    expect(within(proof).getByText('Anna Berg')).toBeInTheDocument()
    expect(await within(proof).findByRole('img', { name: 'Signature' })).toHaveAttribute('src', 'blob:signature')
    expect(within(proof).getByText('Aucune photo')).toBeInTheDocument()

    expect(await screen.findByRole('link', { name: 'Carton écrasé' })).toHaveAttribute('href', '/claims/01CLAIM')
    expect(screen.getByRole('link', { name: /Signaler un problème/ })).toHaveAttribute('href', '/claims/new?orderId=01ORDER&orderServiceId=01SVC1')
  })

  it('shows a proof without signature nor photo, and no delivery note when none exists', async () => {
    server.use(
      http.get(`${API}/orders/01ORDER/services/01SVC1/documents`, () => HttpResponse.json({ data: [], meta: {} })),
      http.get(`${API}/orders/01ORDER/services/01SVC1/proofs-of-delivery`, () => HttpResponse.json({ data: [makeProof({ signature: null })], meta: {} })),
      http.get(`${API}/claims`, () => HttpResponse.json(claims([]))),
    )

    renderApp('/orders/01ORDER/services/01SVC1', makeContext(ALL))

    expect(await screen.findByText('Aucune signature')).toBeInTheDocument()
    expect(screen.getByText('Aucune photo')).toBeInTheDocument()
    expect(await screen.findByText('Aucun document pour ce service')).toBeInTheDocument()
    expect(screen.queryByRole('list', { name: 'Bons de livraison' })).not.toBeInTheDocument()
  })

  it('shows only the sections the member may read', async () => {
    renderApp('/orders/01ORDER/services/01SVC1', makeContext(['customer.orders.view']))

    expect(await screen.findByRole('heading', { name: 'S1 — Livraison' })).toBeInTheDocument()
    expect(screen.queryByText('Preuve de livraison')).not.toBeInTheDocument()
    expect(screen.queryByText('Réclamations')).not.toBeInTheDocument()
  })

  it('presents a service of another order as not found', async () => {
    renderApp('/orders/01ORDER/services/01QOQA', makeContext(ALL))

    expect(await screen.findByText('Service introuvable')).toBeInTheDocument()
  })

  it('adds delivery and claims tabs to the order detail, and links each service to its page', async () => {
    server.use(
      http.get(`${API}/orders/01ORDER/proofs-of-delivery`, () => HttpResponse.json({ data: [makeProof()], meta: {} })),
      http.get(`${API}/claims`, ({ request }) => {
        expect(new URL(request.url).searchParams.get('orderId')).toBe('01ORDER')
        return HttpResponse.json(claims([makeClaim()]))
      }),
    )
    renderApp('/orders/01ORDER', makeContext(ALL))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('tab', { name: 'Livraison' }))
    expect(await screen.findByText('Anna Berg')).toBeInTheDocument()

    await user.click(screen.getByRole('tab', { name: 'Réclamations' }))
    expect(await screen.findByRole('link', { name: 'Carton écrasé' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Créer une réclamation/ })).toHaveAttribute('href', '/claims/new?orderId=01ORDER')

    await user.click(screen.getByRole('tab', { name: /Services/ }))
    expect(screen.getByRole('link', { name: /Documents, livraison et réclamations/ })).toHaveAttribute('href', '/orders/01ORDER/services/01SVC1')
  })

  it('reports a proof list error', async () => {
    server.use(
      http.get(`${API}/orders/01ORDER/services/01SVC1/proofs-of-delivery`, () => HttpResponse.json({ message: 'Vous n’avez pas la permission requise pour cette action.' }, { status: 403 })),
    )

    renderApp('/orders/01ORDER/services/01SVC1', makeContext(['customer.orders.view', 'customer.orders.proofs_of_delivery.view']))

    expect(await screen.findByText('Vous n’avez pas la permission requise pour cette action.')).toBeInTheDocument()
  })
})
