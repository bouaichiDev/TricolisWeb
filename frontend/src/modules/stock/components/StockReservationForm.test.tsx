import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it, vi } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { StockReservationForm } from './StockReservationForm'
import {
  toStockReservationPayload,
  type StockReservationFormValues,
} from '../schemas/stockReservationSchema'
import {
  CUSTOMER_ID,
  ITEM_ID,
  LOCATION_ID,
  ORDER_LINE_ID,
  serveCustomers,
  serveStatuses,
  stockBalance,
  stockItem,
} from '../testSupport'

const ORDER_ID = '01JQZ00000000000000ORDR1'

function serve(balances: unknown[] = [stockBalance()]) {
  const balanceCalls: URL[] = []

  serveCustomers()
  serveStatuses()
  server.use(
    http.get(`${API}/stock-items`, () => HttpResponse.json(paginated([stockItem()]))),
    http.get(`${API}/stock-balances`, ({ request }) => {
      balanceCalls.push(new URL(request.url))

      return HttpResponse.json(paginated(balances))
    }),
    http.get(`${API}/orders`, () =>
      HttpResponse.json(
        paginated([
          {
            id: ORDER_ID,
            orderNumber: 'CMD-0001',
            customerReference: 'REF-9',
            externalReference: null,
            customerId: CUSTOMER_ID,
            status: 'confirmed',
          },
        ]),
      ),
    ),
    http.get(`${API}/orders/${ORDER_ID}`, () =>
      HttpResponse.json({
        data: {
          id: ORDER_ID,
          orderNumber: 'CMD-0001',
          customerId: CUSTOMER_ID,
          lines: [
            {
              id: ORDER_LINE_ID,
              orderId: ORDER_ID,
              articleCode: 'PAL-EUR',
              name: 'Palette Europe',
              quantity: '50.000',
              reservedQuantity: '0.000',
              fromCatalog: false,
            },
          ],
        },
        meta: [],
      }),
    ),
  )

  return balanceCalls
}

function render(onSubmit: (values: StockReservationFormValues) => Promise<unknown>) {
  renderWithProviders(
    <StockReservationForm onSubmit={onSubmit} onCancel={() => {}} submitLabel="Réserver" />,
    { membership: withPermissions(['stock_reservations.create']) },
  )
}

/** Client → commande → ligne → article : l'ordre qu'impose la contrainte serveur. */
async function fillPath() {
  await userEvent.click(screen.getByLabelText('Client'))
  await userEvent.click(await screen.findByRole('option', { name: /Client Nord/ }))

  await userEvent.click(screen.getByLabelText(/^Commande/))
  await userEvent.click(await screen.findByRole('option', { name: /CMD-0001/ }))

  await userEvent.click(await screen.findByLabelText(/^Ligne de commande/))
  await userEvent.click(await screen.findByRole('option', { name: /PAL-EUR/ }))

  await userEvent.click(screen.getByLabelText(/^Code article/))
  await userEvent.click(await screen.findByRole('option', { name: /PAL-EUR/ }))
}

describe('formulaire de réservation', () => {
  /**
   * Les commandes ne sont proposées qu'une fois le client choisi :
   * `OrderLine.order.customerId` doit valoir `StockItem.customerId`, et le
   * serveur refuse le contraire.
   */
  it('n’ouvre les commandes qu’après le client', async () => {
    serve()
    render(async () => {})

    // Deux champs en dépendent — la commande et l'article — et chacun dit
    // pourquoi il est inerte plutôt que de rester vide sans explication.
    expect(await screen.findAllByText('Choisissez d’abord un client.')).toHaveLength(2)
    expect(screen.getByLabelText(/^Commande/)).toBeDisabled()
    expect(screen.getByLabelText(/^Code article/)).toBeDisabled()
  })

  /**
   * Les emplacements viennent des **soldes**, filtrés sur le disponible :
   * réserver là où l'article n'est pas serait refusé faute de disponible.
   */
  it('propose les emplacements où il reste du disponible', async () => {
    const balanceCalls = serve()
    render(async () => {})

    await fillPath()

    await userEvent.click(await screen.findByLabelText(/^Emplacement/))
    expect(await screen.findByRole('option', { name: /A-01-2-3/ })).toBeInTheDocument()

    await expect
      .poll(() =>
        balanceCalls.some(
          (url) =>
            url.searchParams.get('stockItemId') === ITEM_ID &&
            url.searchParams.get('availableOnly') === '1',
        ),
      )
      .toBe(true)
  })

  it('dit quand l’article n’est disponible nulle part', async () => {
    serve([])
    render(async () => {})

    await fillPath()

    expect(
      await screen.findByText('Cet article n’est disponible nulle part.'),
    ).toBeInTheDocument()
  })

  it('soumet les cinq champs que le serveur attend', async () => {
    serve()
    const onSubmit = vi.fn<(values: StockReservationFormValues) => Promise<void>>(async () => {})
    render(onSubmit)

    await fillPath()

    await userEvent.click(await screen.findByLabelText(/^Emplacement/))
    await userEvent.click(await screen.findByRole('option', { name: /A-01-2-3/ }))
    await userEvent.type(screen.getByLabelText(/^Quantité/), '30')

    await userEvent.click(screen.getByRole('button', { name: 'Réserver' }))
    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))

    expect(toStockReservationPayload(onSubmit.mock.calls[0][0])).toEqual({
      stockItemId: ITEM_ID,
      stockLocationId: LOCATION_ID,
      orderLineId: ORDER_LINE_ID,
      quantity: 30,
      status: 'active',
    })
  })

  /**
   * Le disponible n'est **pas** vérifié à l'écran : il change entre l'affichage
   * et l'envoi. `CreateStockReservationAction` le relit sous verrou et répond
   * 409 ; c'est lui qui tranche, pas la saisie.
   */
  it('laisse partir une quantité supérieure au disponible affiché', async () => {
    serve()
    const onSubmit = vi.fn<(values: StockReservationFormValues) => Promise<void>>(async () => {})
    render(onSubmit)

    await fillPath()

    await userEvent.click(await screen.findByLabelText(/^Emplacement/))
    await userEvent.click(await screen.findByRole('option', { name: /A-01-2-3/ }))
    await userEvent.type(screen.getByLabelText(/^Quantité/), '999')

    await userEvent.click(screen.getByRole('button', { name: 'Réserver' }))
    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1))
  })

  it('refuse une quantité nulle', async () => {
    serve()
    const onSubmit = vi.fn<(values: StockReservationFormValues) => Promise<void>>(async () => {})
    render(onSubmit)

    await fillPath()

    await userEvent.click(await screen.findByLabelText(/^Emplacement/))
    await userEvent.click(await screen.findByRole('option', { name: /A-01-2-3/ }))
    await userEvent.type(screen.getByLabelText(/^Quantité/), '0')

    await userEvent.click(screen.getByRole('button', { name: 'Réserver' }))

    expect(
      await screen.findByText('La quantité d’un mouvement doit être strictement positive.'),
    ).toBeInTheDocument()
    expect(onSubmit).not.toHaveBeenCalled()
  })
})
