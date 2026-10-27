import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { StockAdjustmentRequestDetailPage } from './StockAdjustmentRequestDetailPage'
import {
  ADJUSTMENT_ID,
  adjustmentDetail,
  approvedMovement,
  serveAdjustment,
  serveAdjustmentStatuses,
} from '../adjustmentTestSupport'
import { MOVEMENT_ID } from '../testSupport'

const render = () =>
  renderWithProviders(<StockAdjustmentRequestDetailPage />, {
    membership: withPermissions(['stock_adjustment_requests.view', 'stock_adjustment_requests.review']),
    route: `/stock/adjustment-requests/${ADJUSTMENT_ID}`,
    routePath: '/stock/adjustment-requests/:id',
  })

describe('fiche d’une demande de correction', () => {
  it('présente la demande : quantités, écart, motif, commentaire', async () => {
    serveAdjustment()
    render()

    expect(await screen.findByText('Quantité affichée au client')).toBeInTheDocument()
    expect(screen.getByText('47.5')).toBeInTheDocument()
    expect(screen.getByText('−2.5')).toBeInTheDocument()
    expect(screen.getByText('Inventaire du 30 septembre')).toBeInTheDocument()
    expect(screen.getByText('Deux cartons abîmés')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  /** `drift` est calculé par le serveur : l'écran ne fait que le signaler. */
  it('alerte quand le stock a bougé depuis la demande', async () => {
    serveAdjustment(adjustmentDetail({ currentQuantity: '45.000', drift: '-5.000' }))
    render()

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('50 affichés au client, 45 aujourd’hui')
  })

  it('montre les deux décisions quand le serveur les permet', async () => {
    serveAdjustment()
    render()

    expect(await screen.findByRole('button', { name: 'Accepter' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Refuser' })).toBeInTheDocument()
  })

  /**
   * Les actions viennent du serveur, jamais recalculées : même avec la
   * permission `review`, une demande qu'il déclare non tranchable n'offre rien.
   */
  it('masque les actions quand canApprove et canReject sont faux', async () => {
    serveAdjustment(adjustmentDetail({ actions: { canApprove: false, canReject: false } }))
    render()

    await screen.findByText('Quantité affichée au client')
    expect(screen.queryByRole('button', { name: 'Accepter' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Refuser' })).not.toBeInTheDocument()
  })

  it('affiche la décision rendue et le lien vers le mouvement créé', async () => {
    serveAdjustment(
      adjustmentDetail({
        status: 'approved',
        reviewer: { id: 'u1', name: 'Badr Ouali' },
        reviewedAt: '2026-09-30T10:00:00+00:00',
        reviewComment: 'Cartons retrouvés.',
        stockMovementId: MOVEMENT_ID,
        stockMovement: approvedMovement,
        corrections: [],
        actions: { canApprove: false, canReject: false },
      }),
    )
    render()

    expect(await screen.findByText('Badr Ouali')).toBeInTheDocument()
    expect(screen.getByText('Cartons retrouvés.')).toBeInTheDocument()
    // L'en-tête et la section « Décision » portent chacun le badge.
    expect(await screen.findAllByText('Acceptée')).toHaveLength(2)
    expect(screen.getByRole('link', { name: 'adjustment · 2.5' })).toHaveAttribute(
      'href',
      `/stock/movements/${MOVEMENT_ID}`,
    )
  })

  it.each([
    [404, 'Demande introuvable.'],
    [403, 'Action non autorisée.'],
  ])('affiche proprement une réponse %i', async (status, message) => {
    serveAdjustmentStatuses()
    server.use(
      http.get(`${API}/stock-adjustment-requests/${ADJUSTMENT_ID}`, () =>
        HttpResponse.json({ message }, { status }),
      ),
    )
    render()

    expect(await screen.findByText(message)).toBeInTheDocument()
  })
})
