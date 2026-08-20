import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { OrderDetailPage } from './OrderDetailPage'
import { makeOrderDetail, ORDER_ID, PACKAGE_TREE } from './orderDetailFixtures'
import type { OrderDetail } from '../types/orderDetail'

/**
 * `useParams` lit l'identifiant depuis la route : la page est donc montée sous
 * son vrai chemin, comme en production.
 */
function renderDetail(order: OrderDetail, permissions: string[]) {
  server.use(
    http.get(`${API}/orders/${ORDER_ID}`, () => HttpResponse.json({ data: order, meta: [] })),
    http.get(`${API}/orders/${ORDER_ID}/packages/tree`, () =>
      HttpResponse.json({ data: PACKAGE_TREE, meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/history`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/orders/${ORDER_ID}/services/:serviceId/packages`, () =>
      HttpResponse.json({ data: [], meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/documents`, () => HttpResponse.json(paginated([]))),
  )

  return renderWithProviders(<OrderDetailPage />, {
    membership: withPermissions(permissions),
    route: `/orders/${ORDER_ID}`,
    routePath: '/orders/:id',
  })
}

describe('OrderDetailPage', () => {
  it('affiche la commande et ses six onglets', async () => {
    renderDetail(makeOrderDetail(), ['orders.view'])

    expect(await screen.findByRole('heading', { name: 'CMD-2026-000001' })).toBeInTheDocument()

    for (const tab of ['Résumé', 'Lignes', 'Colis', 'Services', 'Documents', 'Historique']) {
      // Le nom accessible porte le compteur : « Lignes 1 ».
      expect(screen.getByRole('tab', { name: new RegExp(`^${tab}`) })).toBeInTheDocument()
    }
  })

  /** Le modèle ne comporte pas d'entité `OrderStop` : l'onglet ne doit pas exister. */
  it('n’affiche aucun onglet « Arrêts »', async () => {
    renderDetail(makeOrderDetail(), ['orders.view'])

    await screen.findByRole('heading', { name: 'CMD-2026-000001' })

    expect(screen.queryByRole('tab', { name: /Arrêts/i })).not.toBeInTheDocument()
  })

  it('distingue une ligne issue du catalogue d’une saisie libre', async () => {
    renderDetail(makeOrderDetail(), ['orders.view'])

    await userEvent.click(await screen.findByRole('tab', { name: /^Lignes/ }))

    // Le libellé paraît deux fois : en titre de la ligne, puis dans le détail
    // complet de ses champs.
    expect((await screen.findAllByText('Carton renforcé')).length).toBeGreaterThan(0)
    expect(screen.getByText('Article du catalogue')).toBeInTheDocument()
  })

  /** L'arbre vient du serveur : le frontend ne recompose pas la hiérarchie. */
  it('affiche la hiérarchie des colis et l’essentiel de chacun', async () => {
    renderDetail(makeOrderDetail(), ['orders.view'])

    await userEvent.click(await screen.findByRole('tab', { name: /^Colis/ }))

    expect((await screen.findAllByText('PAL-1')).length).toBeGreaterThan(0)
    expect(screen.getAllByText('CTN-1').length).toBeGreaterThan(0)
    // Le résumé porte le type, le poids et le volume, sans rien déplier.
    expect(screen.getByText('Palette')).toBeInTheDocument()
    expect(screen.getByText('12.5')).toBeInTheDocument()
  })

  /** Une vignette par service : de quoi reconnaître celui qu'on cherche. */
  it('affiche une vignette par service', async () => {
    renderDetail(makeOrderDetail(), ['orders.view'])

    await userEvent.click(await screen.findByRole('tab', { name: /^Services/ }))

    expect(await screen.findByText('Livraison standard')).toBeInTheDocument()
    expect(screen.getByText('SRV-1')).toBeInTheDocument()
    expect(screen.getByText('Service 1')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Ouvrir le détail/ })).toBeInTheDocument()
  })

  /**
   * Le cycle complet est visible, mais seules les transitions du backend sont
   * sélectionnables : montrer les dix statuts renseigne, en laisser choisir un
   * hors d'atteinte produirait un 409.
   */
  it('montre les dix statuts et ne rend sélectionnables que les transitions permises', async () => {
    renderDetail(makeOrderDetail(), ['orders.view', 'orders.change_status'])

    await userEvent.click(await screen.findByRole('button', { name: /Changer le statut/i }))
    await userEvent.click(await screen.findByRole('combobox'))

    const listbox = await screen.findByRole('listbox')

    expect(within(listbox).getAllByRole('option')).toHaveLength(10)

    expect(within(listbox).getByRole('option', { name: /^Confirmée/ })).not.toHaveAttribute(
      'aria-disabled',
      'true',
    )
    expect(within(listbox).getByRole('option', { name: /^Annulée/ })).not.toHaveAttribute(
      'aria-disabled',
      'true',
    )

    // Facturée est montrée, mais posée par la facturation, pas à la main.
    const invoiced = within(listbox).getByRole('option', { name: /^Facturée/ })
    expect(invoiced).toHaveAttribute('aria-disabled', 'true')
    expect(invoiced).toHaveTextContent(/pas à la main/i)
  })

  it('dit qu’aucune transition n’est possible quand la liste est vide', async () => {
    renderDetail(makeOrderDetail({ allowedTransitions: [] }), [
      'orders.view',
      'orders.change_status',
    ])

    await userEvent.click(await screen.findByRole('button', { name: /Changer le statut/i }))

    expect(
      await screen.findByText(/Aucune transition de statut disponible/i),
    ).toBeInTheDocument()
  })

  /**
   * `allowsContentChanges` est calculé par le backend : passé un certain
   * statut, la commande n'est plus modifiable, et l'écran ne doit pas proposer
   * une action que l'API refusera.
   */
  it('retire la modification et la suppression quand le contenu est figé', async () => {
    renderDetail(makeOrderDetail({ allowsContentChanges: false, status: 'planned' }), [
      'orders.view',
      'orders.update',
      'orders.delete',
    ])

    await screen.findByRole('heading', { name: 'CMD-2026-000001' })

    expect(screen.queryByRole('link', { name: 'Modifier' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument()
    expect(screen.getByText(/le contenu de cette commande est figé/i)).toBeInTheDocument()
  })

  it('offre la modification tant que le contenu est ouvert', async () => {
    renderDetail(makeOrderDetail(), ['orders.view', 'orders.update'])

    expect(await screen.findByRole('link', { name: 'Modifier' })).toBeInTheDocument()
  })

  /** Sans `orders.change_status`, l'action n'apparaît pas. */
  it('masque les actions dont la permission manque', async () => {
    renderDetail(makeOrderDetail(), ['orders.view'])

    await screen.findByRole('heading', { name: 'CMD-2026-000001' })

    expect(screen.queryByRole('button', { name: /Changer le statut/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Dupliquer/i })).not.toBeInTheDocument()
  })

  /** Un refus métier porte un message rédigé : il est affiché tel quel. */
  it('affiche le message d’un refus 409 sur le changement de statut', async () => {
    renderDetail(makeOrderDetail(), ['orders.view', 'orders.change_status'])

    server.use(
      http.patch(`${API}/orders/${ORDER_ID}/status`, () =>
        HttpResponse.json(
          { message: 'Transition impossible depuis le statut actuel.' },
          { status: 409 },
        ),
      ),
    )

    await userEvent.click(await screen.findByRole('button', { name: /Changer le statut/i }))
    await userEvent.click(await screen.findByRole('combobox'))
    await userEvent.click(await screen.findByRole('option', { name: /^Confirmée/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Appliquer' }))

    await waitFor(() => {
      expect(
        screen.getByText('Transition impossible depuis le statut actuel.'),
      ).toBeInTheDocument()
    })
  })

  it('propose les cinq options de duplication acceptées par l’API', async () => {
    renderDetail(makeOrderDetail(), ['orders.view', 'orders.duplicate'])

    await userEvent.click(await screen.findByRole('button', { name: /Dupliquer la commande/i }))

    for (const option of ['Lignes', 'Colis', 'Services', 'Contacts des services', 'Documents']) {
      expect(await screen.findByRole('checkbox', { name: option })).toBeInTheDocument()
    }
  })
})
