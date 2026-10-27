import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { StockAdjustmentRequestDetailPage } from '../../pages/StockAdjustmentRequestDetailPage'
import {
  ADJUSTMENT_ID,
  adjustmentDetail,
  OTHER_LOCATION_ID,
  serveAdjustment,
  wholeItemDetail,
} from '../../adjustmentTestSupport'

const render = () =>
  renderWithProviders(<StockAdjustmentRequestDetailPage />, {
    membership: withPermissions(['stock_adjustment_requests.view', 'stock_adjustment_requests.review']),
    route: `/stock/adjustment-requests/${ADJUSTMENT_ID}`,
    routePath: '/stock/adjustment-requests/:id',
  })

/** Capture le corps envoyé à une décision, et répond comme le serveur. */
function captureDecision(action: 'approve' | 'reject', response?: Response) {
  const sent: unknown[] = []
  server.use(
    http.post(`${API}/stock-adjustment-requests/${ADJUSTMENT_ID}/${action}`, async ({ request }) => {
      sent.push(await request.json())

      return response ?? HttpResponse.json({ data: adjustmentDetail({ actions: { canApprove: false, canReject: false } }), meta: [] })
    }),
  )

  return sent
}

async function openDialog(name: 'Accepter' | 'Refuser') {
  await userEvent.click(await screen.findByRole('button', { name }))

  return screen.findByRole('dialog')
}

describe('accepter une demande', () => {
  it('récapitule le mouvement calculé par le serveur, sans choix d’emplacement', async () => {
    serveAdjustment()
    const sent = captureDecision('approve')
    render()

    const dialog = await openDialog('Accepter')
    const recap = within(dialog).getByTestId('correction-recap')
    expect(recap).toHaveTextContent('Sortie de stock')
    expect(recap).toHaveTextContent('2.5')
    expect(recap).toHaveTextContent('A-01-2-3')
    expect(recap).toHaveTextContent('adjustment')
    expect(within(dialog).queryByRole('combobox')).not.toBeInTheDocument()

    await userEvent.click(within(dialog).getByRole('button', { name: 'Accepter' }))
    await waitFor(() => expect(sent).toEqual([{}]))
  })

  it('exige un emplacement pour une demande « tout l’article »', async () => {
    serveAdjustment(wholeItemDetail())
    const sent = captureDecision('approve')
    render()

    const dialog = await openDialog('Accepter')
    const submit = within(dialog).getByRole('button', { name: 'Accepter' })
    expect(submit).toBeDisabled()

    await userEvent.click(within(dialog).getByRole('combobox', { name: 'Emplacement à corriger' }))
    await userEvent.click(await screen.findByRole('option', { name: /B-02/ }))
    expect(within(dialog).getByTestId('correction-recap')).toHaveTextContent('70 (dont 10 réservés)')

    await userEvent.type(within(dialog).getByLabelText(/Réponse au client/), 'Corrigé en B-02.')
    await userEvent.click(submit)
    await waitFor(() =>
      expect(sent).toEqual([{ stockLocationId: OTHER_LOCATION_ID, reviewComment: 'Corrigé en B-02.' }]),
    )
  })

  it('annonce une acceptation sans mouvement quand le stock correspond déjà', async () => {
    serveAdjustment(adjustmentDetail({ corrections: [{ ...adjustmentDetail().corrections[0], direction: 'none', quantity: '0.000' }] }))
    render()

    const dialog = await openDialog('Accepter')
    expect(within(dialog).getByTestId('correction-recap')).toHaveTextContent('sans mouvement')
  })

  it('affiche le 409 du serveur sans fermer le dialogue', async () => {
    serveAdjustment()
    captureDecision(
      'approve',
      HttpResponse.json({ message: 'Stock disponible insuffisant : 5.000 disponible, 10.000 demandé.' }, { status: 409 }),
    )
    render()

    const dialog = await openDialog('Accepter')
    await userEvent.click(within(dialog).getByRole('button', { name: 'Accepter' }))

    expect(await within(dialog).findByRole('alert')).toHaveTextContent(
      'Décision impossible. Stock disponible insuffisant',
    )
  })
})

describe('refuser une demande', () => {
  it('exige une réponse avant d’envoyer', async () => {
    serveAdjustment()
    const sent = captureDecision('reject')
    render()

    const dialog = await openDialog('Refuser')
    const submit = within(dialog).getByRole('button', { name: 'Refuser' })
    expect(submit).toBeDisabled()

    await userEvent.type(within(dialog).getByLabelText(/obligatoire/), 'Recompté : le stock est juste.')
    expect(submit).toBeEnabled()
    await userEvent.click(submit)

    await waitFor(() => expect(sent).toEqual([{ reviewComment: 'Recompté : le stock est juste.' }]))
  })

  it('affiche le 409 d’une demande déjà traitée', async () => {
    serveAdjustment()
    captureDecision(
      'reject',
      HttpResponse.json({ message: 'Cette demande de correction a déjà été traitée.' }, { status: 409 }),
    )
    render()

    const dialog = await openDialog('Refuser')
    await userEvent.type(within(dialog).getByLabelText(/obligatoire/), 'Non.')
    await userEvent.click(within(dialog).getByRole('button', { name: 'Refuser' }))

    expect(await within(dialog).findByRole('alert')).toHaveTextContent('déjà été traitée')
  })
})
