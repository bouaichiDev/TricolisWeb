import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { StockLocationListPage } from './StockLocationListPage'

const AGENCY_ID = '01JQZ0000000000000000AGY1'
const DEPOT_ID = '01JQZ0000000000000DEPO01'
const LOCATION_ID = '01JQZ0000000000000LOCA01'

const location = {
  id: LOCATION_ID,
  depotId: DEPOT_ID,
  parentLocationId: null,
  zoneCode: 'A',
  aisle: '01',
  rack: '2',
  level: '3',
  locationCode: 'A-01-2-3',
  barcode: null,
  status: 'active',
}

function serveScope() {
  server.use(
    http.get(`${API}/stock-locations`, () => HttpResponse.json(paginated([location]))),
    http.get(`${API}/agencies`, () =>
      HttpResponse.json(
        paginated([
          {
            id: AGENCY_ID,
            organizationId: '01JQZ0000000000000000ORG1',
            code: 'AG01',
            name: 'Agence Nord',
            status: 'active',
            createdAt: '2026-02-01T10:00:00.000000Z',
            updatedAt: '2026-02-01T10:00:00.000000Z',
          },
        ]),
      ),
    ),
    http.get(`${API}/agencies/${AGENCY_ID}/depots`, () =>
      HttpResponse.json(
        paginated([
          {
            id: DEPOT_ID,
            agencyId: AGENCY_ID,
            code: 'DEP01',
            name: 'Dépôt Casablanca',
            status: 'active',
            createdAt: '2026-02-01T10:00:00.000000Z',
            updatedAt: '2026-02-01T10:00:00.000000Z',
          },
        ]),
      ),
    ),
  )
}

const render = (permissions: string[]) =>
  renderWithProviders(<StockLocationListPage />, { membership: withPermissions(permissions) })

describe('emplacements de stock', () => {
  it('liste les emplacements avec leurs coordonnées', async () => {
    serveScope()
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
   * Le dépôt se choisit par son agence : `/agencies/{agency}/depots` est la
   * seule route qui les liste, la dépendance est la forme de l'API.
   */
  it('crée un emplacement sous le dépôt d’une agence', async () => {
    serveScope()

    let body: unknown = null
    server.use(
      http.post(`${API}/stock-locations`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: location, meta: [] }, { status: 201 })
      }),
    )

    render(['stock_locations.view', 'stock_locations.create'])
    await userEvent.click(await screen.findByRole('button', { name: /Nouvel emplacement/ }))

    const dialog = within(await screen.findByRole('dialog'))

    await userEvent.click(dialog.getByLabelText(/^Agence/))
    await userEvent.click(await screen.findByRole('option', { name: /Agence Nord/ }))
    await userEvent.click(dialog.getByLabelText(/^Dépôt/))
    await userEvent.click(await screen.findByRole('option', { name: /Dépôt Casablanca/ }))
    await userEvent.type(dialog.getByLabelText(/^Code emplacement/), 'B-02-1-1')
    await userEvent.type(dialog.getByLabelText(/^Zone/), 'B')
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({
      depotId: DEPOT_ID,
      locationCode: 'B-02-1-1',
      zoneCode: 'B',
      status: 'active',
    })
  })

  /** Sans permission d'écriture, la liste reste consultable et rien d'autre. */
  it('masque la création et la suppression sans les permissions', async () => {
    serveScope()
    render(['stock_locations.view'])

    await screen.findByText('A-01-2-3')

    expect(screen.queryByRole('button', { name: /Nouvel emplacement/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument()
  })
})
