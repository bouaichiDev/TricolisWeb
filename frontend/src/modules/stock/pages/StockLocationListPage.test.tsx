import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { StockLocationListPage } from './StockLocationListPage'
import {
  CHILD_LOCATION_ID,
  DEPOT_ID,
  LOCATION_ID,
  serveScope,
  serveStatuses,
  stockLocation,
} from '../testSupport'

function serveList(rows: unknown[] = [stockLocation()]) {
  const calls: URL[] = []

  serveScope()
  serveStatuses()
  server.use(
    http.get(`${API}/stock-locations`, ({ request }) => {
      calls.push(new URL(request.url))

      return HttpResponse.json(paginated(rows))
    }),
  )

  return calls
}

const render = (permissions: string[]) =>
  renderWithProviders(<StockLocationListPage />, { membership: withPermissions(permissions) })

describe('emplacements de stock', () => {
  it('liste les emplacements avec leurs coordonnées', async () => {
    serveList()
    render(['stock_locations.view'])

    expect(await screen.findByText('A-01-2-3')).toBeInTheDocument()

    // Zone, allée, travée, niveau : les quatre coordonnées du diagramme.
    const row = screen.getByText('A-01-2-3').closest('tr')
    expect(row).not.toBeNull()
    for (const coordinate of ['A', '01', '2', '3']) {
      expect(within(row as HTMLElement).getByText(coordinate)).toBeInTheDocument()
    }
  })

  /**
   * Le tri et la recherche sont délégués au serveur : trier les vingt-cinq
   * lignes affichées donnerait un ordre faux dès la deuxième page.
   */
  it('délègue la recherche au serveur', async () => {
    const calls = serveList()
    render(['stock_locations.view'])

    await screen.findByText('A-01-2-3')
    await userEvent.type(screen.getByLabelText('Rechercher'), 'B-02')

    await expect
      .poll(() => calls.some((url) => url.searchParams.get('search') === 'B-02'))
      .toBe(true)
  })

  /**
   * Le statut vient du référentiel, pas d'une liste codée : `Bloqué` n'est
   * connu que parce que `GET /statuses?source=stock_location` le renvoie.
   */
  it('affiche le libellé de statut du référentiel', async () => {
    serveList([stockLocation({ status: 'blocked' })])
    render(['stock_locations.view'])

    expect(await screen.findByText('Bloqué')).toBeInTheDocument()
  })

  /**
   * L'arbre n'est pas paginé : `GET /stock-locations/tree` charge tout le
   * dépôt. Sans dépôt choisi, l'écran le dit au lieu de charger le parc entier.
   */
  it('exige un dépôt avant de montrer l’arbre', async () => {
    serveList()
    render(['stock_locations.view'])

    await screen.findByText('A-01-2-3')
    await userEvent.click(screen.getByRole('tab', { name: 'Arbre' }))

    expect(await screen.findByText('Choisissez un dépôt')).toBeInTheDocument()
  })

  it('déroule la hiérarchie une fois le dépôt choisi', async () => {
    serveList()
    server.use(
      http.get(`${API}/stock-locations/tree`, () =>
        HttpResponse.json({
          data: [
            {
              id: LOCATION_ID,
              depotId: DEPOT_ID,
              parentLocationId: null,
              zoneCode: 'A',
              locationCode: 'A-01',
              status: 'active',
              children: [
                {
                  id: CHILD_LOCATION_ID,
                  depotId: DEPOT_ID,
                  parentLocationId: LOCATION_ID,
                  zoneCode: 'A',
                  locationCode: 'A-01-2',
                  status: 'active',
                  children: [],
                },
              ],
            },
          ],
          meta: [],
        }),
      ),
    )

    render(['stock_locations.view'])
    await screen.findByText('A-01-2-3')

    await userEvent.click(screen.getByLabelText(/^Agence/))
    await userEvent.click(await screen.findByRole('option', { name: /Agence Nord/ }))
    await userEvent.click(screen.getByLabelText(/^Dépôt/))
    await userEvent.click(await screen.findByRole('option', { name: /Dépôt Casablanca/ }))

    await userEvent.click(screen.getByRole('tab', { name: 'Arbre' }))

    // La racine est ouverte d'office : c'est le niveau où l'on cherche une zone.
    expect(await screen.findByText('A-01')).toBeInTheDocument()
    expect(await screen.findByText('A-01-2')).toBeInTheDocument()
  })

  /** Sans permission d'écriture, la liste reste consultable et rien d'autre. */
  it('masque la création sans la permission', async () => {
    serveList()
    render(['stock_locations.view'])

    await screen.findByText('A-01-2-3')

    expect(screen.queryByRole('link', { name: /Nouvel emplacement/ })).not.toBeInTheDocument()
  })
})
