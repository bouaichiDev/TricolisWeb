import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'
import { paginated } from '@/test/stockFixtures'

import type { CatalogItem } from '../types'

const VIEW = ['customer.catalog.view', 'customer.articles.view']
const catalog = { id: '01CATALOG', code: 'main', name: 'Catalogue IKEA', status: 'active', isActive: true, itemsCount: 1 }
const item: CatalogItem = {
  id: '01ITEM', catalog: { id: '01CATALOG', code: 'main', name: 'Catalogue IKEA' }, articleCode: 'BILLY-80', barcode: '7612345678901',
  name: 'Bibliothèque Billy', description: null, weight: '12.500', volume: '0.2500', length: null, width: null, height: null,
  assemblyTimeMinutes: 20, status: 'active', isActive: true, createdAt: '2026-10-01T10:00:00+02:00',
}

beforeEach(() => {
  server.use(
    http.get(`${API}/catalog/catalogs`, () => HttpResponse.json({ data: [catalog], meta: {} })),
    http.get(`${API}/catalog/items`, () => HttpResponse.json(paginated([item]))),
  )
})

describe('catalog', () => {
  it('lists the articles and shows the menu entry as an active link', async () => {
    renderApp('/catalog', makeContext(VIEW))

    expect(await screen.findByRole('link', { name: 'BILLY-80' })).toBeInTheDocument()
    expect(screen.getByRole('navigation', { name: 'Navigation principale' })).toContainElement(screen.getAllByRole('link', { name: /Catalogue/ })[0])
    expect(screen.queryByRole('button', { name: /Ajouter un article/ })).not.toBeInTheDocument()
  })

  it('adds an article to the catalogue, then opens its page', async () => {
    let body: Record<string, unknown> | null = null
    server.use(
      http.post(`${API}/catalog/items`, async ({ request }) => {
        body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({ data: { ...item, id: '01NEW', articleCode: 'HEMNES-1', name: 'Commode Hemnes' }, meta: {} }, { status: 201 })
      }),
      http.get(`${API}/catalog/items/01NEW`, () => HttpResponse.json({ data: { ...item, id: '01NEW', articleCode: 'HEMNES-1', name: 'Commode Hemnes', usage: { orderLines: 0, stockItems: 0 } }, meta: {} })),
    )
    renderApp('/catalog', makeContext([...VIEW, 'customer.articles.create']))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: /Ajouter un article/ }))
    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText('Code article'), 'HEMNES-1')
    await user.type(within(dialog).getByLabelText('Désignation'), 'Commode Hemnes')
    await user.type(within(dialog).getByLabelText('Poids (kg)'), '12,5')
    await user.click(within(dialog).getByRole('button', { name: 'Ajouter' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toEqual({ catalogId: '01CATALOG', articleCode: 'HEMNES-1', name: 'Commode Hemnes', barcode: null, description: null, weight: '12.5', volume: null })
    expect(await screen.findByRole('heading', { name: 'HEMNES-1' })).toBeInTheDocument()
  })

  it('shows the server refusal of a duplicate code on the field', async () => {
    server.use(http.post(`${API}/catalog/items`, () => HttpResponse.json({ message: 'Invalide', errors: { articleCode: ['Ce code article existe déjà dans ce catalogue.'] } }, { status: 422 })))
    renderApp('/catalog', makeContext([...VIEW, 'customer.articles.create']))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: /Ajouter un article/ }))
    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText('Code article'), 'BILLY-80')
    await user.type(within(dialog).getByLabelText('Désignation'), 'Doublon')
    await user.click(within(dialog).getByRole('button', { name: 'Ajouter' }))

    expect(await within(dialog).findByText('Ce code article existe déjà dans ce catalogue.')).toBeInTheDocument()
  })

  it('refuses the catalogue without its permissions', async () => {
    renderApp('/catalog', makeContext(['customer.orders.view']))

    expect(await screen.findByText(/accès refusé|Accès refusé/i)).toBeInTheDocument()
  })
})
