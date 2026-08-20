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
   * Cinq libellés par ligne poussaient les colonnes de données hors de l'écran.
   * Le libellé survit dans `title` et `aria-label` : l'action reste trouvable
   * au survol et nommée au lecteur d'écran, sans occuper la largeur.
   */
  it('n’affiche que les icônes dans la colonne Actions, libellées au survol', async () => {
    renderDetail(['orders.view'])

    await userEvent.click(await screen.findByRole('tab', { name: /^Colis/ }))
    await screen.findByText('PAL-1')

    const action = screen.getAllByRole('button', { name: 'Contenu du colis' })[0]

    expect(action).toHaveAttribute('title', 'Contenu du colis')
    expect(action).toHaveTextContent('')
  })

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

    await userEvent.click(screen.getAllByRole('button', { name: 'Contenu du colis' })[0])

    // Le contenu du colis — la relation colis ↔ ligne — est dans le tiroir.
    expect(await screen.findByText('Carton renforcé')).toBeInTheDocument()
  })

  /**
   * Deux services d'une même commande portent souvent le même nom — un
   * chargement et une livraison « Montage ». L'adresse est ce qui les
   * distingue : elle est sur la vignette, pas seulement dans le panneau. Le
   * crayon aussi, pour corriger sans passer par le détail.
   */
  it('montre l’adresse et le crayon sur la vignette d’un service', async () => {
    renderDetail(['orders.view', 'order_services.update'])

    await userEvent.click(await screen.findByRole('tab', { name: /^Services/ }))

    expect(await screen.findByText('Entrepôt Casablanca')).toBeInTheDocument()
    expect(screen.getByText('20000 Casablanca')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Modifier le service' })).toBeInTheDocument()
  })

  /** Sans la permission, le crayon disparaît — il n'est pas grisé. */
  it('masque le crayon sans order_services.update', async () => {
    renderDetail(['orders.view'])

    await userEvent.click(await screen.findByRole('tab', { name: /^Services/ }))
    await screen.findByText('Entrepôt Casablanca')

    expect(screen.queryByRole('button', { name: 'Modifier le service' })).not.toBeInTheDocument()
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
