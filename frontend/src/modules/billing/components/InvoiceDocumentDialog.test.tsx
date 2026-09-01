import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { InvoiceDocumentDialog } from './InvoiceDocumentDialog'

const INVOICE_ID = '01JQZ00000000000000INV01'

function render(document: Record<string, unknown>) {
  server.use(
    http.get(`${API}/invoices/${INVOICE_ID}/document`, () =>
      HttpResponse.json({ data: document, meta: [] }),
    ),
  )

  return renderWithProviders(
    <InvoiceDocumentDialog invoiceId={INVOICE_ID} onClose={() => {}} />,
    { membership: withPermissions(['invoices.view']) },
  )
}

describe('aperçu du document d’une facture', () => {
  /**
   * Le HTML vient du serveur, jamais d'un moteur en JavaScript : le §0.20
   * l'interdit, et il montrerait un document différent de celui que le client
   * recevra.
   *
   * Il s'affiche dans une iframe cloisonnée : un modèle est écrit par des
   * utilisateurs, et un `<script>` glissé dedans s'exécuterait sinon avec la
   * session de celui qui ouvre l'aperçu.
   */
  it('rend le HTML du serveur dans une iframe sans script', async () => {
    render({
      html: '<h1>Facture INV-1</h1>',
      templateId: '01JQZ0000000000000TMPL01',
      templateName: 'Facture standard',
      scope: 'global',
      isFrozen: false,
      renderedAt: null,
    })

    const frame = await screen.findByTitle('Document de la facture')

    expect(frame).toHaveAttribute('sandbox', '')
    expect(frame).toHaveAttribute('srcdoc', '<h1>Facture INV-1</h1>')
  })

  /** Sans cette mention, on ne saurait pas si le modèle client a été ignoré. */
  it('nomme la portée du modèle employé', async () => {
    render({
      html: '<p>x</p>',
      templateId: '01JQZ0000000000000TMPL02',
      templateName: 'Facture IKEA',
      scope: 'customer',
      isFrozen: false,
      renderedAt: null,
    })

    expect(await screen.findByText('Modèle du client')).toBeInTheDocument()
    expect(screen.getByText('Facture IKEA')).toBeInTheDocument()
  })

  /** Aucun modèle configuré n'est pas une erreur : la mise en page livrée sert. */
  it('dit quand aucun modèle n’est configuré', async () => {
    render({
      html: '<p>x</p>',
      templateId: null,
      templateName: null,
      scope: 'fallback',
      isFrozen: false,
      renderedAt: null,
    })

    expect(await screen.findByText(/Aucun modèle de facture n’est configuré/)).toBeInTheDocument()
  })

  /** Une facture close ne se re-rend jamais avec le modèle du moment. */
  it('signale un document figé', async () => {
    render({
      html: '<p>x</p>',
      templateId: '01JQZ0000000000000TMPL01',
      templateName: null,
      scope: 'frozen',
      isFrozen: true,
      renderedAt: '2026-08-02T10:00:00+00:00',
    })

    expect(await screen.findByText(/Facture clôturée/)).toBeInTheDocument()
  })
})
