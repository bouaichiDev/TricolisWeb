import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { StockReservationDetailPage } from './StockReservationDetailPage'
import { RESERVATION_ID, serveStatuses, stockReservation } from '../testSupport'

function serve(overrides: Record<string, unknown> = {}) {
  serveStatuses()
  server.use(
    http.get(`${API}/stock-reservations/${RESERVATION_ID}`, () =>
      HttpResponse.json({ data: stockReservation(overrides), meta: [] }),
    ),
  )
}

const render = (permissions: string[] = ['stock_reservations.view', 'stock_reservations.release']) =>
  renderWithProviders(<StockReservationDetailPage />, {
    membership: withPermissions(permissions),
    route: `/stock/reservations/${RESERVATION_ID}`,
    routePath: '/stock/reservations/:id',
  })

describe('fiche de réservation', () => {
  it('affiche la quantité mise de côté et son statut', async () => {
    serve()
    render()

    expect(await screen.findByText('20')).toBeInTheDocument()
    // Le libellé arrive avec le référentiel : `active` s'affiche d'abord tel
    // quel, puis devient « Active » quand `GET /statuses` a répondu.
    expect(await screen.findByText('Active')).toBeInTheDocument()
    expect(screen.getByText('En cours')).toBeInTheDocument()
  })

  /**
   * Une réservation se libère, elle ne se supprime pas : la route n'expose
   * aucun `DELETE`, et la ligne reste comme trace de ce qui fut promis.
   */
  it('ne propose jamais la suppression', async () => {
    serve()
    render()

    await screen.findByText('20')
    expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument()
  })

  /**
   * `ReleaseStockReservationRequest` n'accepte que `status` : ni la date, ni la
   * quantité rendue, qui sont décidées par l'action sous verrou.
   */
  it('n’envoie que le statut à la libération', async () => {
    serve()

    let body: unknown = null
    server.use(
      http.post(`${API}/stock-reservations/${RESERVATION_ID}/release`, async ({ request }) => {
        body = await request.json()

        return HttpResponse.json({
          data: stockReservation({ status: 'released', releasedAt: '2026-08-21T08:00:00+00:00' }),
          meta: [],
        })
      }),
    )

    render()
    await screen.findByText('20')

    await userEvent.click(screen.getByRole('button', { name: 'Libérer' }))
    const dialog = await screen.findByRole('dialog')
    await userEvent.click(within(dialog).getByRole('button', { name: 'Libérer' }))

    await waitFor(() => expect(body).toEqual({ status: 'released' }))
  })

  /**
   * Le bouton disparaît une fois `releasedAt` renseignée. Ce n'est qu'une
   * commodité — deux onglets ouverts la contourneraient — et c'est le 409 du
   * serveur qui interdit réellement la seconde libération.
   */
  it('retire le bouton d’une réservation déjà libérée', async () => {
    serve({ status: 'released', releasedAt: '2026-08-21T08:00:00+00:00' })
    render()

    expect(await screen.findByText('Libérée')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Libérer' })).not.toBeInTheDocument()
  })

  /** Sans la permission de libération, la fiche reste consultable, sans action. */
  it('masque la libération sans la permission', async () => {
    serve()
    render(['stock_reservations.view'])

    await screen.findByText('20')
    expect(screen.queryByRole('button', { name: 'Libérer' })).not.toBeInTheDocument()
  })
})
