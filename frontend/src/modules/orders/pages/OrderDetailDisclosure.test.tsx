import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { OrderDetailPage } from './OrderDetailPage'
import { makeOrderDetail, ORDER_ID, PACKAGE_TREE } from './orderDetailFixtures'

/**
 * Ce qui vit sous le repli.
 *
 * Une ligne porte une vingtaine de champs, un colis une quinzaine, un service
 * autant : les afficher tous noyait les trois qu'on lit vraiment. Le résumé
 * reste visible, le reste s'ouvre à la demande.
 */
function renderDetail(permissions: string[]) {
  server.use(
    http.get(`${API}/orders/${ORDER_ID}`, () =>
      HttpResponse.json({ data: makeOrderDetail(), meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/packages/tree`, () =>
      HttpResponse.json({ data: PACKAGE_TREE, meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/history`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/orders/${ORDER_ID}/documents`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/audit-logs`, () => HttpResponse.json(paginated([]))),
  )

  return renderWithProviders(<OrderDetailPage />, {
    membership: withPermissions(permissions),
    route: `/orders/${ORDER_ID}`,
    routePath: '/orders/:id',
  })
}

describe('détail replié', () => {
  /**
   * Une quinzaine de champs par colis : ils vivent sous le repli, avec le
   * contenu du colis — la relation colis ↔ ligne.
   */
  it('révèle les dimensions et le contenu du colis une fois déplié', async () => {
    renderDetail(['orders.view'])

    await userEvent.click(await screen.findByRole('tab', { name: 'Colis' }))
    await screen.findAllByText('PAL-1')

    expect(screen.queryByText('120')).not.toBeInTheDocument()

    await userEvent.click(screen.getAllByRole('button', { name: /Plus de détails/ })[0])

    expect(await screen.findByText('120')).toBeInTheDocument()
    // La ligne affectée est nommée, pas montrée sous forme d'identifiant.
    expect(screen.getByText('Carton renforcé')).toBeInTheDocument()
  })

  it('révèle contacts, colis et montants une fois le service déplié', async () => {
    renderDetail(['orders.view'])

    await userEvent.click(await screen.findByRole('tab', { name: 'Services' }))
    await userEvent.click(await screen.findByRole('button', { name: /Plus de détails/ }))

    expect(await screen.findByText(/Sophie Bernard/)).toBeInTheDocument()
    expect(screen.getByText('+212600000000')).toBeInTheDocument()
    expect(screen.getByText('PAL-1')).toBeInTheDocument()
  })
})
