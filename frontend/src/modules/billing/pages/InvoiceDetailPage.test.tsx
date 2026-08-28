import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { InvoiceDetailPage } from './InvoiceDetailPage'

const INVOICE_ID = '01JQZ00000000000000INV1'

const invoice = (overrides: Record<string, unknown> = {}) => ({
  id: INVOICE_ID,
  organizationId: '01JQZ0000000000000000ORG1',
  customerId: '01JQZ0000000000000CUSTO1',
  customer: { id: '01JQZ0000000000000CUSTO1', code: 'MIG', name: 'Migros Genève', status: 'active' },
  invoiceNumber: 'INV-2026-00042',
  invoiceDate: '2026-08-31',
  periodFrom: '2026-08-01',
  periodTo: '2026-08-31',
  currencyCode: 'CHF',
  subtotal: '100.00',
  taxTotal: '8.10',
  total: '108.10',
  externalReference: null,
  remark: null,
  status: 'draft',
  createdAt: '2026-08-31T09:00:00+00:00',
  lines: [
    {
      id: '01JQZ0000000000000LINE01',
      invoiceId: INVOICE_ID,
      orderServiceId: '01JQZ000000000000OSRV01',
      orderId: '01JQZ00000000000000ORD1',
      lineNumber: 1,
      serviceCode: 'DEL',
      description: 'Livraison Genève',
      customerOrderReference: 'REF-1',
      quantity: '2.000',
      unitPrice: '50.00',
      discountRate: '0.00',
      taxRate: '8.10',
      totalExcludingTax: '100.00',
      totalIncludingTax: '108.10',
      serviceCompletedAt: null,
      status: 'billable',
      addressSnapshot: {
        addressCode: 'ADR-1',
        name: 'Migros',
        addressLine1: 'Rue du Rhône 12',
        addressLine2: null,
        postalCode: '1204',
        city: 'Genève',
        country: 'CH',
      },
    },
  ],
  ...overrides,
})

function render(
  overrides: Record<string, unknown> = {},
  destinations: Record<string, unknown>[] = [],
  closable = true,
) {
  const closes: string[] = []

  server.use(
    http.get(`${API}/invoices/:id`, () => HttpResponse.json({ data: invoice(overrides) })),
    http.get(`${API}/invoices/:id/closure`, () =>
      HttpResponse.json({ data: { closable, lineCount: 1, destinations } }),
    ),
    http.post(`${API}/invoices/:id/close`, ({ params }) => {
      closes.push(String(params.id))

      return HttpResponse.json({
        data: { invoice: invoice({ status: 'closed' }), exportJobs: [] },
      })
    }),
  )

  renderWithProviders(<InvoiceDetailPage />, {
    membership: withPermissions(['invoices.view', 'invoices.update', 'invoices.close']),
    route: `/billing/invoices/${INVOICE_ID}`,
    routePath: '/billing/invoices/:id',
  })

  return { closes }
}

describe('fiche facture', () => {
  it('montre les lignes et l’adresse du cliché', async () => {
    render()

    expect(await screen.findByText('Livraison Genève')).toBeInTheDocument()
    expect(screen.getByText('1204 Genève')).toBeInTheDocument()
  })

  /**
   * §22 : une facture clôturée est peut-être déjà chez le client. L'écran cesse
   * d'en proposer les actions plutôt que de les laisser échouer au clic.
   */
  it('retire les actions d’écriture sur une facture clôturée', async () => {
    render({ status: 'closed' })

    await screen.findByText('Livraison Genève')

    expect(screen.queryByRole('button', { name: 'Clôturer' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument()
  })
})

describe('clôture', () => {
  /** §52 : savoir où la facture partira avant de la figer. */
  it('annonce les destinations avant de confirmer', async () => {
    render({}, [
      { id: '01JQZ0000000000000CONF01', name: 'API Migros', transport: 'rest_api', format: 'json' },
    ])

    await userEvent.click(await screen.findByRole('button', { name: /Clôturer/ }))

    expect(await screen.findByText('API Migros')).toBeInTheDocument()
    expect(screen.getByText('rest_api')).toBeInTheDocument()
  })

  /** §28 : un client sans intégration reste facturable. */
  it('laisse clôturer sans destination, et le dit', async () => {
    const { closes } = render()

    await userEvent.click(await screen.findByRole('button', { name: /Clôturer/ }))

    expect(await screen.findByText(/sans envoi/)).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Clôturer et envoyer' }))

    await waitFor(() => expect(closes).toEqual([INVOICE_ID]))
  })

  /** §8 : une facture vide n'a rien à figer — le bouton ne doit pas mentir. */
  it('refuse de confirmer une facture que le serveur juge non clôturable', async () => {
    render({}, [], false)

    await userEvent.click(await screen.findByRole('button', { name: /Clôturer/ }))

    expect(await screen.findByText(/au moins une ligne/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Clôturer et envoyer' })).toBeDisabled()
  })
})
