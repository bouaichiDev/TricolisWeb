import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { StockBalanceListPage } from './StockBalanceListPage'
import { serveCustomers, stockBalance } from '../testSupport'

function serveList(rows: unknown[] = [stockBalance()]) {
  const calls: URL[] = []

  serveCustomers()
  server.use(
    http.get(`${API}/stock-balances`, ({ request }) => {
      calls.push(new URL(request.url))

      return HttpResponse.json(paginated(rows))
    }),
  )

  return calls
}

const render = () =>
  renderWithProviders(<StockBalanceListPage />, {
    membership: withPermissions(['stock_balances.view']),
  })

describe('soldes de stock', () => {
  /**
   * `availableQuantity = quantity - reservedQuantity`, calculée par le serveur
   * à chaque écriture. L'écran l'affiche, il ne la recalcule pas — un calcul
   * local divergerait au premier arrondi.
   */
  it('affiche quantité, réservée et disponible', async () => {
    serveList()
    render()

    expect(await screen.findByText('PAL-EUR')).toBeInTheDocument()
    expect(screen.getByText('100')).toBeInTheDocument()
    expect(screen.getByText('20')).toBeInTheDocument()
    expect(screen.getByText('80')).toBeInTheDocument()
  })

  /**
   * Les décimaux arrivent en chaînes — `"2.250"` — parce qu'un flottant les
   * déforme. L'affichage retire les zéros inutiles sans toucher à la valeur.
   */
  it('affiche les décimales sans zéros inutiles', async () => {
    serveList([
      stockBalance({ quantity: '2.250', reservedQuantity: '0.000', availableQuantity: '2.250' }),
    ])
    render()

    await screen.findByText('PAL-EUR')
    expect(screen.getAllByText('2.25').length).toBeGreaterThan(0)
    expect(screen.getByText('0')).toBeInTheDocument()
  })

  /**
   * Lecture seule, et c'est structurel : `StockBalancePolicy` n'expose que
   * `viewAny` et `view`, aucune route n'écrit un solde.
   */
  it('ne propose ni création, ni modification, ni suppression', async () => {
    serveList()
    render()

    await screen.findByText('PAL-EUR')

    for (const label of [/Nouveau/, /Créer/, /Modifier/, /Supprimer/]) {
      expect(screen.queryByRole('button', { name: label })).not.toBeInTheDocument()
      expect(screen.queryByRole('link', { name: label })).not.toBeInTheDocument()
    }
  })

  /** `availableOnly` part en `1` : la règle `boolean` de Laravel refuse `true`. */
  it('transmet le filtre « disponible seulement » au serveur', async () => {
    const calls = serveList()
    render()

    await screen.findByText('PAL-EUR')
    await userEvent.click(screen.getByLabelText('Disponible seulement'))

    await expect
      .poll(() => calls.some((url) => url.searchParams.get('availableOnly') === '1'))
      .toBe(true)
  })

  /** `StockBalanceListQuery` n'applique aucune recherche : ne pas en montrer. */
  it('n’affiche pas de champ de recherche', async () => {
    serveList()
    render()

    await screen.findByText('PAL-EUR')
    expect(screen.queryByLabelText('Rechercher')).not.toBeInTheDocument()
  })
})
