import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { ADMIN_PERMISSIONS, makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'

const ORDER_PERMISSIONS = [...ADMIN_PERMISSIONS, 'customer.orders.view', 'customer.orders.create']
const actions = { canView: true, canEdit: false, canDuplicate: true, canCancel: false, canTrack: false }
const status = { code: 'planned', label: 'Planifiée', icon: null }

const listItem = { id: '01ORDER', reference: 'IKEA-2026-000001', customerReference: 'IKEA-4587', externalReference: null, orderDate: '2026-09-28T10:00:00Z', firstRequestedDate: '2026-10-01', status, source: 'customer_portal', servicesCount: 1, packageCount: 2, weight: '10', createdAt: null, actions }
const options = { catalogEnabled: true, services: [], packageTypes: [], groupingTypes: [], addresses: [], contactRoles: [], statuses: [{ code: 'planned', label: 'Planifiée' }] }

beforeEach(() => {
  server.use(http.get(`${API}/order-form-options`, () => HttpResponse.json({ data: options, meta: {} })))
})

describe('orders pages', () => {
  it('lists the orders and keeps the filters in the URL', async () => {
    let lastQuery = ''
    server.use(
      http.get(`${API}/orders`, ({ request }) => {
        lastQuery = new URL(request.url).search
        return HttpResponse.json({ data: [listItem], meta: { currentPage: 1, perPage: 20, total: 1, lastPage: 1 } })
      }),
    )

    renderApp('/orders?status=planned', makeContext(ORDER_PERMISSIONS))

    expect(await screen.findByRole('link', { name: 'IKEA-2026-000001' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Nouvelle commande/ })).toBeInTheDocument()
    expect(decodeURIComponent(lastQuery)).toContain('status[]=planned')
  })

  it('shows only the actions the backend allows', async () => {
    server.use(
      http.get(`${API}/orders/01ORDER`, () =>
        HttpResponse.json({ data: { ...listItem, workerRemark: null, volume: null, updatedAt: '2026-09-28T10:00:00Z', lines: [], packages: [], services: [] }, meta: {} }),
      ),
    )

    renderApp('/orders/01ORDER', makeContext(ORDER_PERMISSIONS))

    expect(await screen.findByRole('heading', { name: 'IKEA-2026-000001' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Dupliquer/ })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /Modifier/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Annuler/ })).not.toBeInTheDocument()
  })

  it('presents an order of another customer as not found', async () => {
    server.use(http.get(`${API}/orders/:id`, () => HttpResponse.json({ message: 'Introuvable.' }, { status: 404 })))

    renderApp('/orders/01QOQA', makeContext(ORDER_PERMISSIONS))

    expect(await screen.findByText('Commande introuvable')).toBeInTheDocument()
  })

  it('blocks the wizard on an empty service', async () => {
    renderApp('/orders/create', makeContext(ORDER_PERMISSIONS))
    const user = userEvent.setup()

    await screen.findByRole('heading', { name: 'Nouvelle commande' })
    await user.click(screen.getByRole('button', { name: /Suivant/ }))
    await user.click(screen.getByRole('button', { name: /Suivant/ }))

    expect(await screen.findByText('Choisissez un article ou saisissez un libellé.')).toBeInTheDocument()
  })

  it('shows the documents as a list or as cards', async () => {
    const documents = [{ id: '01DOC', fileName: 'bon.pdf', documentType: 'customer_upload', referenceNumber: null, mimeType: 'application/pdf', size: 2048, createdAt: '2026-09-28T10:00:00Z' }]
    server.use(
      http.get(`${API}/orders/01ORDER`, () =>
        HttpResponse.json({ data: { ...listItem, workerRemark: null, volume: null, updatedAt: null, lines: [], packages: [], services: [] }, meta: {} }),
      ),
      http.get(`${API}/orders/01ORDER/documents`, () => HttpResponse.json({ data: documents, meta: {} })),
    )

    renderApp('/orders/01ORDER', makeContext([...ORDER_PERMISSIONS, 'customer.orders.documents.view']))
    const user = userEvent.setup()
    await user.click(await screen.findByRole('tab', { name: 'Documents' }))

    expect(await screen.findByText('application/pdf · 2 Ko')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Vue vignettes' }))
    expect(screen.getByRole('button', { name: 'Vue vignettes' })).toHaveAttribute('aria-pressed', 'true')
    expect(screen.queryByText('application/pdf · 2 Ko')).not.toBeInTheDocument()
    expect(screen.getByText('bon.pdf')).toBeInTheDocument()
  })
})
