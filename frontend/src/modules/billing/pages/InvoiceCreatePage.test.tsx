import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { InvoiceCreatePage } from './InvoiceCreatePage'

const CUSTOMER_ID = '01JQZ0000000000000CUSTO1'
const OTHER_CUSTOMER_ID = '01JQZ0000000000000CUSTO2'

const customer = (id: string, name: string, code: string) => ({
  id,
  code,
  name,
  status: 'active',
  organizationId: '01JQZ0000000000000000ORG1',
})

const service = (overrides: Record<string, unknown> = {}) => ({
  id: '01JQZ000000000000OSRV01',
  serviceNumber: 'S-001',
  orderId: '01JQZ00000000000000ORD1',
  orderNumber: 'CMD-100',
  customerReference: 'REF-1',
  serviceCode: 'DEL',
  serviceName: 'Livraison',
  requestedDate: '2026-08-12',
  quantity: 2,
  unit: 'palette',
  customerUnitPrice: 50,
  customerTotalPrice: 100,
  weight: 12,
  volume: 0.5,
  packageCount: 2,
  status: 'completed',
  address: {
    id: '01JQZ0000000000000ADDR01',
    code: 'ADR-1',
    name: 'Migros',
    addressLine1: 'Rue du Rhône 12',
    postalCode: '1204',
    city: 'Genève',
    country: 'CH',
  },
  ...overrides,
})

function render(services = [service()], failure: { status: number; body: { message: string; errors: Record<string, string[]> } } | null = null) {
  const billableCalls: URL[] = []
  const created: Record<string, unknown>[] = []

  server.use(
    http.get(`${API}/customers`, () =>
      HttpResponse.json(
        paginated([
          customer(CUSTOMER_ID, 'Migros Genève', 'MIG'),
          customer(OTHER_CUSTOMER_ID, 'Coop Lausanne', 'COO'),
        ]),
      ),
    ),
    http.get(`${API}/customers/:id/billable-services`, ({ request }) => {
      billableCalls.push(new URL(request.url))

      return HttpResponse.json(paginated(services))
    }),
    http.post(`${API}/invoices`, async ({ request }) => {
      created.push((await request.json()) as Record<string, unknown>)

      if (failure) return HttpResponse.json(failure.body, { status: failure.status })

      return HttpResponse.json({ data: { id: '01JQZ00000000000000INV1' } }, { status: 201 })
    }),
  )

  renderWithProviders(<InvoiceCreatePage />, {
    membership: withPermissions(['invoices.create', 'invoices.view', 'customers.view']),
  })

  return { billableCalls, created }
}

/** Choisit un client dans la liste déroulante de l'en-tête. */
async function chooseCustomer(name: string) {
  await userEvent.click(screen.getAllByRole('combobox')[0])
  await userEvent.click(await screen.findByRole('option', { name: new RegExp(name) }))
}

describe('composition d’une facture', () => {
  /**
   * Les prestations facturables se demandent client par client : la route
   * n'existe que sous un client, et « toutes les prestations facturables »
   * n'aurait pas de sens.
   */
  it('ne demande rien tant qu’aucun client n’est choisi', async () => {
    const { billableCalls } = render()

    expect(await screen.findByText(/se consultent client par client/)).toBeInTheDocument()
    expect(billableCalls).toHaveLength(0)
  })

  it('liste les prestations du client choisi', async () => {
    const { billableCalls } = render()

    await chooseCustomer('Migros')

    expect(await screen.findByText('S-001')).toBeInTheDocument()
    expect(screen.getByText('CMD-100')).toBeInTheDocument()

    await waitFor(() => expect(billableCalls).toHaveLength(1))
    expect(billableCalls[0].pathname).toContain(CUSTOMER_ID)
  })

  /** §42 : la période est un filtre serveur, pas un tri local. */
  it('borne la période côté serveur', async () => {
    const { billableCalls } = render()

    await chooseCustomer('Migros')
    await screen.findByText('S-001')

    await userEvent.type(screen.getByLabelText('Période du'), '2026-08-01')

    await waitFor(() => {
      const last = billableCalls[billableCalls.length - 1]
      expect(last.searchParams.get('periodFrom')).toBe('2026-08-01')
    })
  })

  /**
   * Une facture ne porte qu'un client. Changer d'avis après avoir retenu des
   * prestations produirait un document mêlant deux destinataires.
   */
  it('fige le client dès la première prestation retenue', async () => {
    render()

    await chooseCustomer('Migros')
    await userEvent.click(await screen.findByRole('checkbox'))

    await waitFor(() => expect(screen.getAllByRole('combobox')[0]).toBeDisabled())
  })

  it('envoie les prestations retenues comme lignes de la facture', async () => {
    const { created } = render()

    await chooseCustomer('Migros')
    await userEvent.click(await screen.findByRole('checkbox'))

    await userEvent.type(screen.getByLabelText('Numéro'), 'INV-2026-001')
    await userEvent.click(screen.getByRole('button', { name: 'Créer la facture' }))

    await waitFor(() => expect(created).toHaveLength(1))

    const payload = created[0] as { customerId: string; status: string; lines: unknown[] }

    expect(payload.customerId).toBe(CUSTOMER_ID)
    // Une facture nait au brouillon : c'est le seul etat ou ses lignes se
    // corrigent encore.
    expect(payload.status).toBe('draft')
    expect(payload.lines).toEqual([
      expect.objectContaining({
        orderServiceId: '01JQZ000000000000OSRV01',
        lineNumber: 1,
        quantity: 2,
        unitPrice: 50,
        description: 'Livraison',
        status: 'billable',
      }),
    ])
  })

  /** §8 : une facture porte au moins une ligne — sans quoi rien à clôturer. */
  it('n’autorise pas une facture sans prestation', async () => {
    render()

    await chooseCustomer('Migros')
    await userEvent.type(screen.getByLabelText('Numéro'), 'INV-2026-001')

    expect(screen.getByRole('button', { name: 'Créer la facture' })).toBeDisabled()
  })

  /**
   * Un numéro déjà pris est le refus le plus courant. Sans relais, le bouton se
   * réactive et rien n’explique pourquoi : l’écran aurait l’air cassé.
   */
  it('montre le refus du serveur', async () => {
    render([service()], {
      status: 422,
      body: {
        message: 'Les données fournies sont invalides.',
        errors: { invoiceNumber: ['Ce numéro de facture existe déjà.'] },
      },
    })

    await chooseCustomer('Migros')
    await userEvent.click(await screen.findByRole('checkbox'))

    await userEvent.type(screen.getByLabelText('Numéro'), 'INV-2026-001')
    await userEvent.click(screen.getByRole('button', { name: 'Créer la facture' }))

    expect(await screen.findByText(/existe déjà/)).toBeInTheDocument()
  })
})
