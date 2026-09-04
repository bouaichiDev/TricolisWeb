import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { StockItemListPage } from './StockItemListPage'
import { CUSTOMER_ID, serveCustomers, serveStatuses, stockItem } from '../testSupport'

function serveList(rows: unknown[] = [stockItem()]) {
  const calls: URL[] = []

  serveStatuses()
  serveCustomers()
  server.use(
    http.get(`${API}/stock-items`, ({ request }) => {
      calls.push(new URL(request.url))

      return HttpResponse.json(paginated(rows))
    }),
  )

  return calls
}

const render = (permissions: string[]) =>
  renderWithProviders(<StockItemListPage />, { membership: withPermissions(permissions) })

describe('articles de stock', () => {
  it('liste les articles avec leur client', async () => {
    serveList()
    render(['stock_items.view'])

    expect(await screen.findByText('PAL-EUR')).toBeInTheDocument()
    expect(screen.getByText('Client Nord')).toBeInTheDocument()
    expect(screen.getByText('Palette Europe')).toBeInTheDocument()
  })

  /**
   * `StockItemListResource` n'expose **aucune** quantité, et aucune route
   * d'agrégat n'existe : une colonne de total demanderait une requête par
   * ligne. Le §42 l'interdit ; les quantités se lisent sur la fiche.
   */
  it('n’affiche aucune colonne de quantité', async () => {
    serveList()
    render(['stock_items.view'])

    await screen.findByText('PAL-EUR')

    for (const header of ['Quantité totale', 'Réservée', 'Disponible']) {
      expect(screen.queryByRole('columnheader', { name: header })).not.toBeInTheDocument()
    }
  })

  it('filtre par client côté serveur', async () => {
    const calls = serveList()
    render(['stock_items.view'])

    await screen.findByText('PAL-EUR')
    await userEvent.click(screen.getByLabelText('Client'))
    await userEvent.click(await screen.findByRole('option', { name: /Client Nord/ }))

    await expect
      .poll(() => calls.some((url) => url.searchParams.get('customerId') === CUSTOMER_ID))
      .toBe(true)
  })

  /**
   * Le tri passe par la liste blanche du serveur, en `snake_case` : toute autre
   * valeur renvoie 422. L'en-tête porte un `aria-label` qui dit le sens du
   * prochain clic, d'où la sélection par son libellé visible.
   */
  it('trie sur les colonnes que le serveur accepte', async () => {
    const calls = serveList()
    render(['stock_items.view'])

    await screen.findByText('PAL-EUR')
    await userEvent.click(screen.getByText('Code-barres'))

    await expect
      .poll(() => calls.some((url) => url.searchParams.get('sort') === 'barcode'))
      .toBe(true)
  })

  it('distingue un article lié au catalogue d’un article libre', async () => {
    serveList([
      stockItem(),
      stockItem({ id: 'other', articleCode: 'CAR-01', catalogItemId: 'cat-1' }),
    ])
    render(['stock_items.view'])

    await screen.findByText('PAL-EUR')
    expect(screen.getByText('Non lié')).toBeInTheDocument()
    expect(screen.getByText('Lié')).toBeInTheDocument()
  })

  it('masque la création sans la permission', async () => {
    serveList()
    render(['stock_items.view'])

    await screen.findByText('PAL-EUR')
    expect(screen.queryByRole('link', { name: /Nouvel article/ })).not.toBeInTheDocument()
  })
})
