import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { SettlementCreatePage } from './SettlementCreatePage'

const PROVIDER_ID = '01JQZ0000000000000PROVI1'

const provider = (id: string, name: string, code: string) => ({
  id,
  organizationId: '01JQZ0000000000000000ORG1',
  addressId: null,
  contactId: null,
  code,
  name,
  status: 'active',
})

const service = (overrides: Record<string, unknown> = {}) => ({
  id: '01JQZ000000000000OSRV01',
  serviceNumber: 'S-001',
  orderId: '01JQZ00000000000000ORD1',
  orderNumber: 'CMD-100',
  customerReference: 'REF-1',
  customerName: 'Migros Genève',
  serviceCode: 'DEL',
  serviceName: 'Livraison',
  requestedDate: '2026-08-12',
  quantity: 2,
  unit: 'palette',
  // Le prix client et le cout fournisseur different : c'est la marge, et elle
  // ne se reverse pas.
  customerUnitPrice: 80,
  providerUnitCost: 50,
  providerTotalCost: 100,
  weight: 12,
  volume: 0.5,
  packageCount: 2,
  status: 'completed',
  address: {
    id: '01JQZ0000000000000ADDR01',
    code: 'ADR-1',
    name: 'Migros',
    postalCode: '1204',
    city: 'Genève',
  },
  ...overrides,
})

function render(services = [service()], failure: { status: number; body: { message: string; errors: Record<string, string[]> } } | null = null) {
  const settleableCalls: URL[] = []
  const created: { url: string; body: Record<string, unknown> }[] = []

  server.use(
    http.get(`${API}/providers`, () =>
      HttpResponse.json(
        paginated([
          provider(PROVIDER_ID, 'Transports Léman', 'LEM'),
          provider('01JQZ0000000000000PROVI2', 'Alpes Express', 'ALP'),
        ]),
      ),
    ),
    http.get(`${API}/providers/:id/settleable-services`, ({ request }) => {
      settleableCalls.push(new URL(request.url))

      return HttpResponse.json(paginated(services))
    }),
    http.post(`${API}/providers/:id/settlements`, async ({ request, params }) => {
      created.push({
        url: String(params.id),
        body: (await request.json()) as Record<string, unknown>,
      })

      if (failure) return HttpResponse.json(failure.body, { status: failure.status })

      return HttpResponse.json({ data: { id: '01JQZ00000000000000SET1' } }, { status: 201 })
    }),
  )

  renderWithProviders(<SettlementCreatePage />, {
    membership: withPermissions(['provider_settlements.create', 'providers.view']),
  })

  return { settleableCalls, created }
}

async function chooseProvider(name: string) {
  await userEvent.click(screen.getAllByRole('combobox')[0])
  await userEvent.click(await screen.findByRole('option', { name: new RegExp(name) }))
}

describe('composition d’un décompte', () => {
  it('ne demande rien tant qu’aucun fournisseur n’est choisi', async () => {
    const { settleableCalls } = render()

    expect(await screen.findByText(/fournisseur par fournisseur/)).toBeInTheDocument()
    expect(settleableCalls).toHaveLength(0)
  })

  it('liste les prestations du fournisseur choisi', async () => {
    const { settleableCalls } = render()

    await chooseProvider('Léman')

    expect(await screen.findByText('S-001')).toBeInTheDocument()
    expect(screen.getByText('Migros Genève')).toBeInTheDocument()

    await waitFor(() => expect(settleableCalls).toHaveLength(1))
    expect(settleableCalls[0].pathname).toContain(PROVIDER_ID)
  })

  /**
   * **Le point qui coûte cher.** Le coût fournisseur et le prix client se
   * ressemblent à l'écran ; régler le second reverserait la marge.
   */
  it('règle le coût fournisseur, pas le prix client', async () => {
    const { created } = render()

    await chooseProvider('Léman')
    await userEvent.click(await screen.findByRole('checkbox'))

    await userEvent.type(screen.getByLabelText('Numéro'), 'DEC-2026-001')
    await userEvent.click(screen.getByRole('button', { name: 'Créer le décompte' }))

    await waitFor(() => expect(created).toHaveLength(1))

    expect(created[0].url).toBe(PROVIDER_ID)
    expect(created[0].body.lines).toEqual([
      expect.objectContaining({
        orderServiceId: '01JQZ000000000000OSRV01',
        quantity: 2,
        unitCost: 50,
      }),
    ])
  })

  /** Un décompte ne paie qu'un fournisseur. */
  it('fige le fournisseur dès la première prestation retenue', async () => {
    render()

    await chooseProvider('Léman')
    await userEvent.click(await screen.findByRole('checkbox'))

    await waitFor(() => expect(screen.getAllByRole('combobox')[0]).toBeDisabled())
  })

  it('n’autorise pas un décompte sans prestation', async () => {
    render()

    await chooseProvider('Léman')
    await userEvent.type(screen.getByLabelText('Numéro'), 'DEC-2026-001')

    expect(screen.getByRole('button', { name: 'Créer le décompte' })).toBeDisabled()
  })

  /** Sans relais, l’écran aurait l’air cassé : le bouton se réactive, et rien
   *  n’explique le refus. */
  it('montre le refus du serveur', async () => {
    render([service()], {
      status: 422,
      body: {
        message: 'Les données fournies sont invalides.',
        errors: { settlementNumber: ['Ce numéro de décompte existe déjà.'] },
      },
    })

    await chooseProvider('Léman')
    await userEvent.click(await screen.findByRole('checkbox'))

    await userEvent.type(screen.getByLabelText('Numéro'), 'DEC-2026-001')
    await userEvent.click(screen.getByRole('button', { name: 'Créer le décompte' }))

    expect(await screen.findByText(/existe déjà/)).toBeInTheDocument()
  })
})
