import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { OrderListPage } from './OrderListPage'

const order = {
  id: '01JQZ00000000000000ORD01',
  orderNumber: 'CMD-2026-000001',
  externalReference: 'EXT-42',
  customerReference: null,
  orderType: null,
  orderDate: '2026-08-01',
  source: 'internal',
  status: 'draft',
  statusLabel: 'Brouillon',
  customerId: '01JQZ000000000000000CUST',
  customerName: 'Client Alpha',
  agencyId: '01JQZ0000000000000000AGY1',
  agencyName: 'Agence Nord',
  linesCount: 2,
  servicesCount: 1,
  weight: null,
  volume: null,
  packageCount: null,
  createdAt: '2026-08-01T09:00:00.000000Z',
}

function captureQueries(rows: unknown[] = [order]) {
  const queries: URLSearchParams[] = []

  server.use(
    http.get(`${API}/orders`, ({ request }) => {
      queries.push(new URL(request.url).searchParams)
      return HttpResponse.json(paginated(rows, { total: rows.length, lastPage: 3 }))
    }),
  )

  return queries
}

describe('OrderListPage', () => {
  it('affiche les commandes renvoyées par l’API', async () => {
    captureQueries()
    renderWithProviders(<OrderListPage />, { membership: withPermissions(['orders.view']) })

    expect(await screen.findByText('CMD-2026-000001')).toBeInTheDocument()
    expect(screen.getByText('Client Alpha')).toBeInTheDocument()
  })

  it('demande la première page triée par date décroissante', async () => {
    const queries = captureQueries()
    renderWithProviders(<OrderListPage />, { membership: withPermissions(['orders.view']) })

    await screen.findByText('CMD-2026-000001')

    expect(queries[0].get('page')).toBe('1')
    expect(queries[0].get('sort')).toBe('order_date')
    expect(queries[0].get('direction')).toBe('desc')
  })

  /**
   * Le tri est serveur : la page envoie la colonne, elle ne réordonne rien.
   *
   * L'en-tête triable porte son propre libellé accessible — « Trier par ordre
   * croissant » — qui remplace le texte de la colonne : c'est par lui qu'on le
   * désigne, la première colonne triable étant le numéro de commande.
   */
  it('transmet le tri au serveur et inverse le sens au second clic', async () => {
    const queries = captureQueries()
    renderWithProviders(<OrderListPage />, { membership: withPermissions(['orders.view']) })

    await screen.findByText('CMD-2026-000001')
    await userEvent.click(screen.getAllByRole('button', { name: /^Trier par ordre/ })[0])

    await waitFor(() => {
      const last = queries[queries.length - 1]
      expect(last.get('sort')).toBe('order_number')
      expect(last.get('direction')).toBe('asc')
    })

    await userEvent.click(screen.getAllByRole('button', { name: /^Trier par ordre/ })[0])

    await waitFor(() => {
      expect(queries[queries.length - 1].get('direction')).toBe('desc')
    })
  })

  it('transmet la recherche et revient en première page', async () => {
    const queries = captureQueries()
    renderWithProviders(<OrderListPage />, { membership: withPermissions(['orders.view']) })

    await screen.findByText('CMD-2026-000001')
    await userEvent.type(screen.getByRole('textbox', { name: 'Rechercher' }), 'CMD-2026')

    await waitFor(
      () => {
        expect(queries.some((query) => query.get('search') === 'CMD-2026')).toBe(true)
      },
      { timeout: 3000 },
    )

    expect(queries[queries.length - 1].get('page')).toBe('1')
  })

  it('demande la page suivante sans recharger localement', async () => {
    const queries = captureQueries()
    renderWithProviders(<OrderListPage />, { membership: withPermissions(['orders.view']) })

    await screen.findByText('CMD-2026-000001')
    await userEvent.click(screen.getByRole('button', { name: 'Suivant' }))

    await waitFor(() => {
      expect(queries[queries.length - 1].get('page')).toBe('2')
    })
  })

  /** `orders.create` gouverne l'accès à la création : sans elle, pas de bouton. */
  it('n’offre la création qu’avec la permission correspondante', async () => {
    captureQueries()
    const { unmount } = renderWithProviders(<OrderListPage />, {
      membership: withPermissions(['orders.view']),
    })

    await screen.findByText('CMD-2026-000001')
    expect(screen.queryByRole('link', { name: /Nouvelle commande/i })).not.toBeInTheDocument()

    unmount()
    captureQueries()
    renderWithProviders(<OrderListPage />, {
      membership: withPermissions(['orders.view', 'orders.create']),
    })

    expect(await screen.findByRole('link', { name: /Nouvelle commande/i })).toBeInTheDocument()
  })
})
