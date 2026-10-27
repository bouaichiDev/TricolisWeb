import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { makeClaim } from '@/test/afterDeliveryFixtures'
import { ADMIN_PERMISSIONS, makeContext, makeUser } from '@/test/fixtures'
import { makeExportJob, makeOverview } from '@/test/integrationFixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'
import { makeStockItem, paginated, stockFilterOptions } from '@/test/stockFixtures'

/**
 * Même structure de liste qu'au back-office, écran par écran : barre de
 * filtres, tableau, choix du nombre de lignes, actions en icônes.
 */
const catalogItem = {
  id: '01ITEM', catalog: { id: '01CATALOG', code: 'main', name: 'Catalogue IKEA' }, articleCode: 'BILLY-80', barcode: null, name: 'Billy',
  description: null, weight: '1.000', volume: '0.0000', length: null, width: null, height: null, assemblyTimeMinutes: null,
  status: 'active', isActive: true, createdAt: null, actions: { canUpdate: true },
}
const orderStatus = { code: 'planned', label: 'Planifiée', icon: null }
const order = { id: '01ORDER', reference: 'IKEA-2026-000001', customerReference: null, externalReference: null, orderDate: '2026-09-28T10:00:00Z', firstRequestedDate: null, status: orderStatus, source: 'customer_portal', servicesCount: 1, packageCount: 0, weight: null, createdAt: null, actions: { canView: true, canEdit: false, canDuplicate: false, canCancel: false, canTrack: false } }
const adjustment = { id: '01ADJ', stockItem: { id: '01ITEM', articleCode: 'ART-001' }, location: null, displayedQuantity: '10.000', requestedQuantity: '9.000', reason: 'Inventaire', comment: null, status: { code: 'pending', label: 'En attente', color: null }, isPending: true, reviewComment: null, createdAt: '2026-10-01T10:00:00Z' }

const screens: Record<string, { route: string; permissions: string[]; mocks: () => void; ready: string | RegExp }> = {
  commandes: { route: '/orders', permissions: [...ADMIN_PERMISSIONS, 'customer.orders.view'], mocks: () => server.use(http.get(`${API}/orders`, () => HttpResponse.json(paginated([order]))), http.get(`${API}/order-form-options`, () => HttpResponse.json({ data: { statuses: [], services: [] }, meta: {} }))), ready: 'IKEA-2026-000001' },
  réclamations: { route: '/claims', permissions: ['customer.claims.view'], mocks: () => server.use(http.get(`${API}/claims`, () => HttpResponse.json(paginated([makeClaim()])))), ready: 'Carton écrasé' },
  stock: { route: '/stock', permissions: ['customer.stock.view'], mocks: () => server.use(http.get(`${API}/stock/summary`, () => HttpResponse.json({ data: { itemsCount: 1, quantity: '1', reservedQuantity: '0', availableQuantity: '1' }, meta: {} })), http.get(`${API}/stock/items`, () => HttpResponse.json(paginated([makeStockItem()], { filters: stockFilterOptions })))), ready: 'ART-001' },
  corrections: { route: '/stock/adjustment-requests', permissions: ['customer.stock.view', 'customer.stock.adjustment_requests.view'], mocks: () => server.use(http.get(`${API}/stock/adjustment-requests`, () => HttpResponse.json(paginated([adjustment])))), ready: 'Inventaire' },
  catalogue: { route: '/catalog', permissions: ['customer.catalog.view', 'customer.articles.view'], mocks: () => server.use(http.get(`${API}/catalog/catalogs`, () => HttpResponse.json({ data: [], meta: {} })), http.get(`${API}/catalog/items`, () => HttpResponse.json(paginated([catalogItem])))), ready: 'Billy' },
  utilisateurs: { route: '/users', permissions: ADMIN_PERMISSIONS, mocks: () => server.use(http.get(`${API}/users`, () => HttpResponse.json(paginated([makeUser()])))), ready: 'viewer@ikea.test' },
}

describe('lists keep the back-office structure', () => {
  it.each(Object.keys(screens))('%s offers the per-page selector', async (name) => {
    const { route, permissions, mocks, ready } = screens[name]
    mocks()
    renderApp(route, makeContext(permissions))

    expect(await screen.findAllByText(ready)).not.toHaveLength(0)
    expect(screen.getByRole('combobox', { name: 'Lignes par page' })).toBeInTheDocument()
  })

  it('asks the server for the chosen number of rows', async () => {
    const asked: string[] = []
    screens.commandes.mocks()
    server.use(http.get(`${API}/orders`, ({ request }) => {
      asked.push(new URL(request.url).searchParams.get('perPage') ?? '')
      return HttpResponse.json(paginated([order]))
    }))
    renderApp('/orders', makeContext(screens.commandes.permissions))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('combobox', { name: 'Lignes par page' }))
    await user.click(await screen.findByRole('option', { name: '50' }))

    await waitFor(() => expect(asked).toContain('50'))
  })

  it('turns the catalogue row actions into icons, by permission', async () => {
    screens.catalogue.mocks()
    renderApp('/catalog', makeContext([...screens.catalogue.permissions, 'customer.articles.update']))

    expect(await screen.findByRole('link', { name: 'Voir' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Modifier' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Désactiver' })).toBeInTheDocument()
  })

  it('turns the export retry into an icon', async () => {
    server.use(
      http.get(`${API}/integrations`, () => HttpResponse.json({ data: makeOverview(), meta: {} })),
      http.get(`${API}/integrations/export-jobs`, () => HttpResponse.json(paginated([makeExportJob({ status: 'failed' })]))),
    )
    renderApp('/integrations', makeContext(['customer.integrations.view', 'customer.integrations.logs.view', 'customer.integrations.export_jobs.retry']))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('tab', { name: /Envois/ }))

    const retry = await screen.findByRole('button', { name: 'Relancer' })
    expect(retry).toHaveAttribute('title', 'Relancer')
    expect(screen.getByRole('combobox', { name: 'Lignes par page' })).toBeInTheDocument()
  })
})
