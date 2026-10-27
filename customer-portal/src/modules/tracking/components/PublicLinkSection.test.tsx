import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'

const ORDER = {
  id: '01ORDER', reference: 'IKEA-2026-000001', customerReference: null, externalReference: null, orderDate: null,
  status: { code: 'planned', label: 'Planifiée', icon: null }, source: null, packageCount: 0, weight: null, createdAt: null,
  workerRemark: null, volume: null, updatedAt: null, lines: [], packages: [], services: [],
  actions: { canView: true, canEdit: false, canDuplicate: false, canCancel: false, canTrack: true },
}
const VIEW = ['customer.orders.view', 'customer.tracking.view', 'customer.orders.track', 'customer.tracking.public_link.view']
const MANAGE = [...VIEW, 'customer.tracking.public_link.manage']
const URL_LINK = `${API}/orders/01ORDER/public-tracking-link`
const PUBLIC_URL = `http://localhost:5180/t/${'a'.repeat(64)}`

beforeEach(() => {
  server.use(http.get(`${API}/orders/01ORDER`, () => HttpResponse.json({ data: ORDER, meta: {} })))
})

describe('public tracking link section', () => {
  it('offers tracking and generates, shows, then revokes the link', async () => {
    let body: unknown = null
    server.use(
      http.get(URL_LINK, () => HttpResponse.json({ data: { exists: false, state: 'none', active: false, publicUrl: null }, meta: {} })),
      http.post(URL_LINK, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: { exists: true, state: 'active', active: true, expiresAt: null, publicUrl: PUBLIC_URL }, meta: {} }, { status: 201 })
      }),
      http.delete(URL_LINK, () => HttpResponse.json({ data: { exists: true, state: 'revoked', active: false, expiresAt: null, publicUrl: null }, meta: {} })),
    )
    vi.spyOn(window, 'confirm').mockReturnValue(true)

    renderApp('/orders/01ORDER', makeContext(MANAGE))
    const user = userEvent.setup()

    expect(await screen.findByRole('link', { name: /Suivre/ })).toHaveAttribute('href', '/orders/01ORDER/tracking')
    await user.click(screen.getByRole('tab', { name: 'Suivi public' }))
    await user.click(await screen.findByRole('button', { name: 'Générer un lien' }))

    expect(await screen.findByRole('textbox', { name: 'Lien de suivi public' })).toHaveValue(PUBLIC_URL)
    expect(body).toEqual({})

    await user.click(screen.getByRole('button', { name: /Révoquer/ }))

    expect(await screen.findByText('Lien révoqué')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Générer un nouveau lien' })).toBeInTheDocument()
  })

  it('shows an active link without its URL, and only a regenerate path', async () => {
    server.use(http.get(URL_LINK, () => HttpResponse.json({ data: { exists: true, state: 'active', active: true, expiresAt: '2026-10-10T23:59:59+02:00', publicUrl: null }, meta: {} })))

    renderApp('/orders/01ORDER', makeContext(MANAGE))
    await userEvent.setup().click(await screen.findByRole('tab', { name: 'Suivi public' }))

    expect(await screen.findByText('Lien actif')).toBeInTheDocument()
    expect(screen.getByText(/Régénérez-le pour l’obtenir/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Régénérer/ })).toBeInTheDocument()
  })

  it('keeps the section in its own tab, hidden without the permission', async () => {
    renderApp('/orders/01ORDER', makeContext(['customer.orders.view', 'customer.tracking.view', 'customer.orders.track']))

    expect(await screen.findByRole('tab', { name: 'Général' })).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Suivi public' })).not.toBeInTheDocument()
    expect(screen.queryByText('Lien de suivi public')).not.toBeInTheDocument()
  })

  it('lets a member without the manage permission only read the state', async () => {
    server.use(http.get(URL_LINK, () => HttpResponse.json({ data: { exists: false, state: 'none', active: false, publicUrl: null }, meta: {} })))

    renderApp('/orders/01ORDER', makeContext(VIEW))
    await userEvent.setup().click(await screen.findByRole('tab', { name: 'Suivi public' }))

    expect(await screen.findByText('Aucun lien')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Générer/ })).not.toBeInTheDocument()
  })
})
