import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'

import type { CatalogItem } from '../types'

const VIEW = ['customer.catalog.view', 'customer.articles.view']
const item: CatalogItem = {
  id: '01ITEM', catalog: { id: '01CATALOG', code: 'main', name: 'Catalogue IKEA' }, articleCode: 'BILLY-80', barcode: '7612345678901',
  name: 'Bibliothèque Billy', description: null, weight: '12.500', volume: '0.2500', length: null, width: null, height: null,
  assemblyTimeMinutes: 20, status: 'active', isActive: true, createdAt: '2026-10-01T10:00:00+02:00',
  usage: { orderLines: 2, stockItems: 1 }, actions: { canUpdate: true },
}

const serve = (article: CatalogItem) => server.use(http.get(`${API}/catalog/items/01ITEM`, () => HttpResponse.json({ data: article, meta: {} })))

describe('catalog item edition', () => {
  it('shows neither edit nor deactivate without the update capability', async () => {
    serve({ ...item, actions: { canUpdate: false } })
    renderApp('/catalog/01ITEM', makeContext(VIEW))

    expect(await screen.findByRole('heading', { name: 'BILLY-80' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Modifier/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Désactiver/ })).not.toBeInTheDocument()
  })

  it('edits the article from a prefilled form, without status nor catalogue', async () => {
    serve(item)
    let body: Record<string, unknown> | null = null
    server.use(http.patch(`${API}/catalog/items/01ITEM`, async ({ request }) => {
      body = (await request.json()) as Record<string, unknown>
      return HttpResponse.json({ data: { ...item, name: 'Billy blanche' }, meta: {} })
    }))
    renderApp('/catalog/01ITEM', makeContext([...VIEW, 'customer.articles.update']))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: /Modifier/ }))
    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).getByLabelText('Code article')).toHaveValue('BILLY-80')
    expect(within(dialog).getByLabelText('Poids (kg)')).toHaveValue('12,5')
    expect(within(dialog).queryByLabelText('Catalogue')).not.toBeInTheDocument()

    await user.clear(within(dialog).getByLabelText('Désignation'))
    await user.type(within(dialog).getByLabelText('Désignation'), 'Billy blanche')
    await user.click(within(dialog).getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toEqual({ articleCode: 'BILLY-80', name: 'Billy blanche', barcode: '7612345678901', description: null, weight: '12.5', volume: '0.25' })
  })

  it('deactivates after confirmation, then offers to reactivate', async () => {
    serve(item)
    let called = false
    server.use(http.post(`${API}/catalog/items/01ITEM/deactivate`, () => {
      called = true
      serve({ ...item, status: 'inactive', isActive: false })
      return HttpResponse.json({ data: { ...item, status: 'inactive', isActive: false }, meta: {} })
    }))
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true)
    renderApp('/catalog/01ITEM', makeContext([...VIEW, 'customer.articles.update']))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: /Désactiver/ }))

    expect(confirm).toHaveBeenCalled()
    await waitFor(() => expect(called).toBe(true))
    expect(await screen.findByRole('button', { name: /Réactiver/ })).toBeInTheDocument()
    confirm.mockRestore()
  })

  it('does nothing when the deactivation is not confirmed', async () => {
    serve(item)
    let called = false
    server.use(http.post(`${API}/catalog/items/01ITEM/deactivate`, () => {
      called = true
      return HttpResponse.json({ data: item, meta: {} })
    }))
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false)
    renderApp('/catalog/01ITEM', makeContext([...VIEW, 'customer.articles.update']))

    await userEvent.setup().click(await screen.findByRole('button', { name: /Désactiver/ }))

    expect(called).toBe(false)
    confirm.mockRestore()
  })
})
