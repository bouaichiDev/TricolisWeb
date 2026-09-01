import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { OrderDetailPage } from '../pages/OrderDetailPage'
import { makeOrderDetail, ORDER_ID, PACKAGE_TREE } from '../pages/orderDetailFixtures'

const status = (code: string, requiresReason: boolean) => ({
  id: `01JQZ000000000000STAT${code.slice(0, 2).toUpperCase()}`,
  source: 'order',
  status: 1,
  code,
  label: code === 'cancelled' ? 'Annulée' : 'Confirmée',
  color: null,
  icon: null,
  isActive: true,
  allowsContentChanges: false,
  requiresReason,
  position: 1,
  createdAt: '2026-08-01T09:00:00+00:00',
  updatedAt: '2026-08-01T09:00:00+00:00',
})

/**
 * Annuler une commande exige un motif — `statuses.requires_reason` le dit, et
 * `ChangeOrderStatus` refuse la transition sans lui.
 *
 * Le dialogue ne le demandait pas : le serveur répondait « un motif est
 * obligatoire » sans que rien à l'écran permette de lui répondre, et annuler
 * une commande était impossible.
 */
function renderDetail(cancelledRequiresReason = true) {
  const sent: unknown[] = []

  server.use(
    http.get(`${API}/orders/${ORDER_ID}`, () =>
      HttpResponse.json({
        data: { ...makeOrderDetail(), status: 'draft', allowedTransitions: ['cancelled'] },
        meta: [],
      }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/packages/tree`, () =>
      HttpResponse.json({ data: PACKAGE_TREE, meta: [] }),
    ),
    http.get(`${API}/orders/${ORDER_ID}/history`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/orders/${ORDER_ID}/documents`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/audit-logs`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/statuses`, () =>
      HttpResponse.json(
        paginated([status('cancelled', cancelledRequiresReason), status('confirmed', false)]),
      ),
    ),
    http.patch(`${API}/orders/${ORDER_ID}/status`, async ({ request }) => {
      sent.push(await request.json())

      return HttpResponse.json({ data: makeOrderDetail(), meta: [] })
    }),
  )

  renderWithProviders(<OrderDetailPage />, {
    membership: withPermissions(['orders.view', 'orders.change_status']),
    route: `/orders/${ORDER_ID}`,
    routePath: '/orders/:id',
  })

  return sent
}

async function aimAtCancelled() {
  await userEvent.click(await screen.findByRole('button', { name: /Changer le statut/ }))

  const dialog = within(await screen.findByRole('dialog'))
  await userEvent.click(dialog.getByLabelText(/Nouveau statut/))
  await userEvent.click(await screen.findByRole('option', { name: /^Annulée/ }))

  return dialog
}

describe('motif de changement de statut', () => {
  it('demande un motif pour un statut qui en exige un', async () => {
    renderDetail()

    const dialog = await aimAtCancelled()

    expect(await dialog.findByLabelText(/^Motif/)).toBeInTheDocument()
  })

  /**
   * Le serveur refuserait la transition ; laisser cliquer ferait revenir une
   * erreur que l'écran pouvait éviter.
   */
  it('empêche d’appliquer tant que le motif est vide', async () => {
    renderDetail()

    const dialog = await aimAtCancelled()
    await dialog.findByLabelText(/^Motif/)

    expect(dialog.getByRole('button', { name: 'Appliquer' })).toBeDisabled()
  })

  it('envoie le motif saisi', async () => {
    const sent = renderDetail()

    const dialog = await aimAtCancelled()
    await userEvent.type(await dialog.findByLabelText(/^Motif/), 'Client injoignable')
    await userEvent.click(dialog.getByRole('button', { name: 'Appliquer' }))

    await waitFor(() => expect(sent).toHaveLength(1))
    expect(sent[0]).toMatchObject({ status: 'cancelled', reasonText: 'Client injoignable' })
  })

  /** Un statut qui n'en exige pas ne doit pas faire saisir un champ inutile. */
  it('ne demande rien pour un statut sans motif exigé', async () => {
    renderDetail(false)

    const dialog = await aimAtCancelled()

    expect(dialog.queryByLabelText(/^Motif/)).not.toBeInTheDocument()
    expect(dialog.getByRole('button', { name: 'Appliquer' })).toBeEnabled()
  })
})
