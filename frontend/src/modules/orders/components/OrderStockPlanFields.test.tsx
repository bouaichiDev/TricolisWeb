import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { OrderDetailPage } from '../pages/OrderDetailPage'
import { LINE_ID, makeOrderDetail, ORDER_ID, PACKAGE_TREE } from '../pages/orderDetailFixtures'

const LOCATION_A = '01JQZ0000000000000LOCA01'
const LOCATION_B = '01JQZ0000000000000LOCA02'

const planLine = (overrides: Record<string, unknown>) => ({
  orderLineId: LINE_ID,
  name: 'Canapé',
  articleCode: 'ART-1',
  quantity: '10.000',
  stockItemId: '01JQZ0000000000000STKI01',
  stockLocationId: null,
  state: 'ambiguous',
  locations: [
    { id: LOCATION_A, locationCode: 'A-01', zoneCode: 'A', availableQuantity: '40.000' },
    { id: LOCATION_B, locationCode: 'B-02', zoneCode: 'B', availableQuantity: '25.000' },
  ],
  ...overrides,
})

/**
 * Confirmer une commande sort sa marchandise du stock.
 *
 * Une ligne ne dit pas où sa marchandise se trouve : quand un article dort dans
 * plusieurs emplacements, l'écran demande lequel vider **avant** d'envoyer,
 * plutôt que de partir pour revenir en 422.
 */
function renderDetail(plan: unknown[]) {
  server.use(
    http.get(`${API}/orders/${ORDER_ID}`, () =>
      HttpResponse.json({
        data: { ...makeOrderDetail(), status: 'draft', allowedTransitions: ['confirmed'] },
        meta: [],
      }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/packages/tree`, () =>
      HttpResponse.json({ data: PACKAGE_TREE, meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/history`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/orders/${ORDER_ID}/documents`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/audit-logs`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/orders/${ORDER_ID}/stock-plan`, () =>
      HttpResponse.json({ data: plan, meta: [] }),
    ),
  )

  return renderWithProviders(<OrderDetailPage />, {
    membership: withPermissions(['orders.view', 'orders.change_status']),
    route: `/orders/${ORDER_ID}`,
    routePath: '/orders/:id',
  })
}

/** Ouvre le dialogue et vise « Confirmée », ce qui déclenche l'aperçu. */
async function aimAtConfirmed() {
  await userEvent.click(await screen.findByRole('button', { name: /Changer le statut/ }))

  const dialog = within(await screen.findByRole('dialog'))
  await userEvent.click(dialog.getByLabelText(/Nouveau statut/))
  await userEvent.click(await screen.findByRole('option', { name: /^Confirmée/ }))

  return dialog
}

describe('sortie de stock à la confirmation', () => {
  it('demande l’emplacement quand l’article dort dans plusieurs endroits', async () => {
    let body: unknown = null
    renderDetail([planLine({})])

    server.use(
      http.patch(`${API}/orders/${ORDER_ID}/status`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: makeOrderDetail(), meta: [] })
      }),
    )

    const dialog = await aimAtConfirmed()

    // Tant que l'emplacement n'est pas choisi, la confirmation est bloquée.
    const submit = dialog.getByRole('button', { name: 'Appliquer' })
    expect(await screen.findByText(/Sortie de stock/)).toBeInTheDocument()
    expect(submit).toBeDisabled()

    await userEvent.click(dialog.getByLabelText(/ART-1/))
    await userEvent.click(await screen.findByRole('option', { name: /B-02/ }))
    await userEvent.click(submit)

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toEqual({
      status: 'confirmed',
      stockLocations: [{ orderLineId: LINE_ID, stockLocationId: LOCATION_B }],
    })
  })

  /** Un seul emplacement : le serveur le trouve, l'écran ne demande rien. */
  it('n’envoie aucun emplacement quand il n’y a rien à trancher', async () => {
    let body: unknown = null
    renderDetail([planLine({ state: 'resolved', stockLocationId: LOCATION_A })])

    server.use(
      http.patch(`${API}/orders/${ORDER_ID}/status`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: makeOrderDetail(), meta: [] })
      }),
    )

    const dialog = await aimAtConfirmed()

    expect(await screen.findByText(/sera sortie du stock/)).toBeInTheDocument()
    await userEvent.click(dialog.getByRole('button', { name: 'Appliquer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toEqual({ status: 'confirmed' })
  })

  /** Stock insuffisant : inutile d'envoyer, le serveur refuserait. */
  it('bloque la confirmation quand le stock ne couvre pas la quantité', async () => {
    renderDetail([planLine({ state: 'insufficient', locations: [] })])

    const dialog = await aimAtConfirmed()

    expect(await screen.findByText(/ne couvre pas la quantité commandée/)).toBeInTheDocument()
    expect(dialog.getByRole('button', { name: 'Appliquer' })).toBeDisabled()
  })
})
