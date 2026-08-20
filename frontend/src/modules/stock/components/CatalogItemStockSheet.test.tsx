import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { CatalogItemStockSheet } from './CatalogItemStockSheet'

const CUSTOMER_ID = '01JQZ000000000000000CUST'
const CATALOG_ITEM_ID = '01JQZ00000000000000ITEM1'
const STOCK_ITEM_ID = '01JQZ0000000000000STKI01'
const LOCATION_ID = '01JQZ0000000000000LOCA01'

const ITEM = {
  id: CATALOG_ITEM_ID,
  articleCode: 'ART-1',
  name: 'Carton renforcé',
  barcode: '3760000000009',
}

const stockItem = {
  id: STOCK_ITEM_ID,
  customerId: CUSTOMER_ID,
  catalogItemId: CATALOG_ITEM_ID,
  articleCode: 'ART-1',
  barcode: '3760000000009',
  description: 'Carton renforcé',
  status: 'active',
}

const location = {
  id: LOCATION_ID,
  depotId: '01JQZ0000000000000DEPO01',
  parentLocationId: null,
  zoneCode: 'A',
  aisle: '01',
  rack: '2',
  level: '3',
  locationCode: 'A-01-2-3',
  barcode: null,
  status: 'active',
}

/** Les emplacements alimentent les libellés des soldes et des mouvements. */
function serveLocations() {
  server.use(http.get(`${API}/stock-locations`, () => HttpResponse.json(paginated([location]))))
}

const PERMISSIONS = [
  'catalogs.view',
  'stock_balances.view',
  'stock_items.create',
  'stock_movements.view',
  'stock_movements.create',
]

const render = (permissions = PERMISSIONS) =>
  renderWithProviders(
    <CatalogItemStockSheet customerId={CUSTOMER_ID} item={ITEM} onOpenChange={() => {}} />,
    { membership: withPermissions(permissions) },
  )

describe('stock d’un article de catalogue', () => {
  /**
   * `StockItem.catalogItemId` est facultatif : un article catalogué n'est pas
   * forcément suivi en dépôt. Le tiroir propose alors de l'y mettre, plutôt que
   * d'afficher un tableau vide sans expliquer pourquoi.
   */
  it('propose le suivi quand l’article n’a pas de référence physique', async () => {
    serveLocations()
    server.use(http.get(`${API}/stock-items`, () => HttpResponse.json(paginated([]))))

    render()

    expect(await screen.findByText(/n’est pas suivi en stock/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Suivre en stock' })).toBeInTheDocument()
  })

  it('crée la référence physique liée à l’article de catalogue', async () => {
    serveLocations()

    let body: unknown = null
    server.use(
      http.get(`${API}/stock-items`, () => HttpResponse.json(paginated([]))),
      http.post(`${API}/customers/${CUSTOMER_ID}/stock-items`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: stockItem, meta: [] }, { status: 201 })
      }),
    )

    render()
    await userEvent.click(await screen.findByRole('button', { name: 'Suivre en stock' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({
      customerId: CUSTOMER_ID,
      catalogItemId: CATALOG_ITEM_ID,
      articleCode: 'ART-1',
    })
  })

  /** La quantité est par emplacement : le tableau la montre ligne par ligne. */
  it('montre les soldes par emplacement et l’historique', async () => {
    serveLocations()
    server.use(
      http.get(`${API}/stock-items`, () => HttpResponse.json(paginated([stockItem]))),
      http.get(`${API}/stock-balances`, () =>
        HttpResponse.json(
          paginated([
            {
              id: '01JQZ0000000000000BAL001',
              stockItemId: STOCK_ITEM_ID,
              stockLocationId: LOCATION_ID,
              quantity: 12,
              reservedQuantity: 2,
              availableQuantity: 10,
              updatedAt: '2026-08-01T09:00:00+00:00',
            },
          ]),
        ),
      ),
      http.get(`${API}/stock-movements`, () =>
        HttpResponse.json(
          paginated([
            {
              id: '01JQZ0000000000000MOV001',
              stockItemId: STOCK_ITEM_ID,
              sourceLocationId: null,
              destinationLocationId: LOCATION_ID,
              movementType: 'reception',
              quantity: 12,
              sourceEntityType: null,
              sourceEntityId: null,
              createdBy: null,
              createdAt: '2026-08-01T09:00:00+00:00',
            },
          ]),
        ),
      ),
    )

    render()

    expect(await screen.findByText('Total 12 · disponible 10')).toBeInTheDocument()
    expect(screen.getAllByText('A · A-01-2-3').length).toBeGreaterThan(0)

    // Sans source, le mouvement est une entrée — déduit, jamais lu dans le type.
    expect(screen.getByText('reception')).toBeInTheDocument()
    expect(screen.getByText('Entrée')).toBeInTheDocument()
  })

  /** Sans la permission, aucun bouton n'ouvre le dialogue de mouvement. */
  it('masque l’enregistrement d’un mouvement sans la permission', async () => {
    serveLocations()
    server.use(
      http.get(`${API}/stock-items`, () => HttpResponse.json(paginated([stockItem]))),
      http.get(`${API}/stock-balances`, () => HttpResponse.json(paginated([]))),
      http.get(`${API}/stock-movements`, () => HttpResponse.json(paginated([]))),
    )

    render(['catalogs.view', 'stock_balances.view', 'stock_movements.view'])

    await screen.findByText('Quantités par emplacement')
    expect(screen.queryByRole('button', { name: 'Nouveau mouvement' })).not.toBeInTheDocument()
  })
})

describe('enregistrement d’un mouvement', () => {
  /**
   * Le sens détermine ce qui part : une entrée n'a pas de source, une sortie
   * pas de destination. `CreateStockMovementAction` refuse un mouvement sans
   * l'un ni l'autre.
   */
  it('n’envoie que l’emplacement qu’implique le sens', async () => {
    serveLocations()

    let body: unknown = null
    server.use(
      http.get(`${API}/stock-items`, () => HttpResponse.json(paginated([stockItem]))),
      http.get(`${API}/stock-balances`, () => HttpResponse.json(paginated([]))),
      http.get(`${API}/stock-movements`, () => HttpResponse.json(paginated([]))),
      http.post(`${API}/stock-movements`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: {}, meta: [] }, { status: 201 })
      }),
    )

    render()
    await userEvent.click(await screen.findByRole('button', { name: 'Nouveau mouvement' }))

    const dialog = within(await screen.findByRole('dialog'))

    // Le sens par défaut est l'entrée : pas d'emplacement d'origine à l'écran.
    expect(dialog.queryByLabelText(/Emplacement d’origine/)).not.toBeInTheDocument()

    await userEvent.click(dialog.getByLabelText(/Emplacement de destination/))
    await userEvent.click(await screen.findByRole('option', { name: /A-01-2-3/ }))
    await userEvent.type(dialog.getByLabelText(/^Quantité/), '12')
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer le mouvement' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({
      stockItemId: STOCK_ITEM_ID,
      sourceLocationId: null,
      destinationLocationId: LOCATION_ID,
      quantity: 12,
    })
  })
})
