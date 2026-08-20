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
 * Ce qui vit dans les panneaux latéraux.
 *
 * Les tableaux portent les quelques valeurs qu'on lit d'un coup d'œil. Le
 * détail — quinze à vingt champs par élément, le contenu d'un colis, les
 * contacts d'un service — s'ouvre à côté plutôt que d'allonger la ligne.
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
    http.get(`${API}/orders/${ORDER_ID}/services/:serviceId/packages`, () =>
      HttpResponse.json({ data: [], meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/documents`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/audit-logs`, () => HttpResponse.json(paginated([]))),
  )

  return renderWithProviders(<OrderDetailPage />, {
    membership: withPermissions(permissions),
    route: `/orders/${ORDER_ID}`,
    routePath: '/orders/:id',
  })
}

describe('panneaux de détail', () => {
  /**
   * Le tableau des colis montre six colonnes ; les dimensions détaillées et le
   * contenu du colis vivent dans le tiroir.
   */
  it('ouvre la fiche complète d’un colis', async () => {
    renderDetail(['orders.view'])

    await userEvent.click(await screen.findByRole('tab', { name: /^Colis/ }))
    await screen.findByText('PAL-1')

    // Le tableau réunit les dimensions en une colonne.
    expect(screen.getByText('120 × 80 × 145')).toBeInTheDocument()
    expect(screen.queryByText('Carton renforcé')).not.toBeInTheDocument()

    await userEvent.click(screen.getAllByRole('button', { name: 'Actions' })[0])
    await userEvent.click(await screen.findByRole('menuitem', { name: 'Contenu du colis' }))

    // Le contenu du colis — la relation colis ↔ ligne — est dans le tiroir.
    expect(await screen.findByText('Carton renforcé')).toBeInTheDocument()
  })

  it('ouvre la fiche complète d’un service', async () => {
    renderDetail(['orders.view'])

    await userEvent.click(await screen.findByRole('tab', { name: /^Services/ }))
    await screen.findByText('Livraison standard')

    // La vignette annonce le contact ; les montants et les colis pris en
    // charge n'apparaissent qu'une fois le panneau ouvert.
    expect(screen.queryByText('PU fournisseur')).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /^Détail/ }))

    expect(await screen.findByText('PU fournisseur')).toBeInTheDocument()
    expect(screen.getAllByText('+212600000000').length).toBeGreaterThan(0)
    // Les colis de la commande sont listés ; sans `order_services.update`, la
    // prise en charge se lit mais ne se coche pas.
    expect(screen.getByText('PAL-1')).toBeInTheDocument()
    expect(screen.queryByRole('checkbox', { name: 'PAL-1' })).not.toBeInTheDocument()
  })

  /** Les six chiffres clés sont visibles sans ouvrir quoi que ce soit. */
  it('affiche le bandeau de chiffres clés', async () => {
    renderDetail(['orders.view'])

    await screen.findByRole('heading', { name: 'CMD-2026-000001' })

    for (const label of ['Poids', 'Volume', 'Colis', 'Lignes', 'Services', 'Total client']) {
      expect(screen.getAllByText(label).length).toBeGreaterThan(0)
    }

    // Total client : somme des services, la commande n'en porte aucun. Il
    // paraît deux fois — en chiffre clé et dans la carte Montants.
    expect(screen.getAllByText('120.00').length).toBeGreaterThan(0)
  })
})
