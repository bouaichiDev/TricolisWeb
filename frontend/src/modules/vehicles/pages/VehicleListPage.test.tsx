import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { VehicleCreatePage } from './VehicleCreatePage'
import { VehicleListPage } from './VehicleListPage'

const PROVIDER_ID = '01JQZ0000000000000PROV01'
const TYPE_ITEM_ID = '01JQZ0000000000000ITEM01'

const vehicle = (overrides: Record<string, unknown> = {}) => ({
  id: '01JQZ0000000000000VEHI01',
  providerId: PROVIDER_ID,
  vehicleTypeId: TYPE_ITEM_ID,
  code: 'VEH-01',
  registrationNumber: '12345-A-6',
  payloadCapacity: '3500.000',
  volumeCapacity: '22.5000',
  palletCapacity: 8,
  status: 'active',
  providerName: 'Transports Atlas',
  vehicleTypeName: 'Camion 19T',
  ...overrides,
})

const status = (code: string, label: string, rank: number) => ({
  id: `01JQZ000000000000STAV0${rank}`,
  source: 'vehicle',
  status: rank,
  code,
  label,
  icon: null,
  active: true,
  isToSend: false,
  allowsContentChanges: false,
  requiresReason: false,
  position: rank * 10,
  createdAt: '2026-08-01T09:00:00+00:00',
  updatedAt: '2026-08-01T09:00:00+00:00',
})

/**
 * Les trois référentiels que l'écran interroge : statuts, fournisseurs, et les
 * valeurs de la source `vehicle` — les types de véhicule vivent désormais dans
 * `type_items`.
 */
function referentials(calls: URL[]) {
  return [
    http.get(`${API}/statuses`, () =>
      HttpResponse.json(
        paginated([status('active', 'Actif', 1), status('maintenance', 'En maintenance', 2)]),
      ),
    ),
    http.get(`${API}/providers`, () =>
      HttpResponse.json(
        paginated([
          {
            id: PROVIDER_ID,
            organizationId: '01JQZ0000000000000000ORG1',
            addressId: null,
            contactId: null,
            code: 'TRANS-01',
            name: 'Transports Atlas',
            status: 'active',
          },
        ]),
      ),
    ),
    http.get(`${API}/type-items`, ({ request }) => {
      calls.push(new URL(request.url))

      return HttpResponse.json(
        paginated([
          {
            id: TYPE_ITEM_ID,
            organizationId: '01JQZ0000000000000000ORG1',
            typeId: '01JQZ0000000000000TYPE01',
            typeCode: 'vehicle',
            code: 'CAM19',
            name: 'Camion 19T',
            status: 'active',
            position: 0,
            createdAt: '2026-08-01T09:00:00+00:00',
            updatedAt: '2026-08-01T09:00:00+00:00',
          },
        ]),
      )
    }),
  ]
}

describe('liste des véhicules', () => {
  const render = (permissions: string[], rows: unknown[] = [vehicle()]) => {
    const calls: URL[] = []

    server.use(
      ...referentials(calls),
      http.get(`${API}/vehicles`, ({ request }) => {
        calls.push(new URL(request.url))

        return HttpResponse.json(paginated(rows))
      }),
    )

    renderWithProviders(<VehicleListPage />, { membership: withPermissions(permissions) })

    return calls
  }

  it('montre le fournisseur, le type et les capacités', async () => {
    render(['vehicles.view'])

    expect(await screen.findByText('VEH-01')).toBeInTheDocument()
    expect(screen.getByText('12345-A-6')).toBeInTheDocument()
    expect(screen.getByText('Transports Atlas')).toBeInTheDocument()
    expect(screen.getAllByText('Camion 19T').length).toBeGreaterThan(0)
    expect(screen.getByText(/3500\.000 kg/)).toBeInTheDocument()
  })

  /** Le type vient de la source `vehicle` : « Palette » n'a rien à faire ici. */
  it('ne demande que les types de véhicule', async () => {
    const calls = render(['vehicles.view'])

    await screen.findByText('VEH-01')

    await waitFor(() =>
      expect(
        calls.some(
          (url) =>
            url.pathname.endsWith('/type-items') && url.searchParams.get('type') === 'vehicle',
        ),
      ).toBe(true),
    )
  })

  it('filtre par type de véhicule', async () => {
    const calls = render(['vehicles.view'])

    await screen.findByText('VEH-01')
    await userEvent.click(screen.getByRole('combobox', { name: /Type de véhicule/ }))
    await userEvent.click(await screen.findByRole('option', { name: /Camion 19T/ }))

    await waitFor(() =>
      expect(
        calls.some(
          (url) =>
            url.pathname.endsWith('/vehicles') &&
            url.searchParams.get('vehicleTypeId') === TYPE_ITEM_ID,
        ),
      ).toBe(true),
    )
  })

  it('masque la création sans la permission', async () => {
    render(['vehicles.view'])

    await screen.findByText('VEH-01')

    expect(screen.queryByRole('link', { name: /Ajouter un véhicule/ })).not.toBeInTheDocument()
  })
})

describe('création d’un véhicule', () => {
  const fill = async () => {
    await userEvent.click(await screen.findByRole('combobox', { name: /Fournisseur/ }))
    await userEvent.click(await screen.findByRole('option', { name: /Transports Atlas/ }))

    await userEvent.click(screen.getByRole('combobox', { name: /Type de véhicule/ }))
    await userEvent.click(await screen.findByRole('option', { name: /Camion 19T/ }))

    await userEvent.type(screen.getByLabelText(/^Code/), 'VEH-NEW')
    await userEvent.type(screen.getByLabelText(/^Immatriculation/), '99999-Z-9')
  }

  it('envoie les capacités en nombres et le statut en code', async () => {
    let body: unknown = null
    const calls: URL[] = []

    server.use(
      ...referentials(calls),
      http.post(`${API}/vehicles`, async ({ request }) => {
        body = await request.json()

        return HttpResponse.json({ data: vehicle(), meta: [] }, { status: 201 })
      }),
    )

    renderWithProviders(<VehicleCreatePage />, {
      membership: withPermissions(['vehicles.create']),
    })

    await fill()
    await userEvent.type(screen.getByLabelText(/^Charge utile/), '3500')
    await userEvent.type(screen.getByLabelText(/^Volume/), '22')
    await userEvent.type(screen.getByLabelText(/^Palettes/), '8')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({
      providerId: PROVIDER_ID,
      vehicleTypeId: TYPE_ITEM_ID,
      code: 'VEH-NEW',
      registrationNumber: '99999-Z-9',
      payloadCapacity: 3500,
      volumeCapacity: 22,
      palletCapacity: 8,
      status: 'active',
    })
  })

  /**
   * Une capacité laissée vide est une erreur, pas une charge utile nulle : une
   * conversion silencieuse enregistrerait `0` sans que personne l'ait voulu.
   */
  it('refuse une capacité laissée vide', async () => {
    let posted = false
    const calls: URL[] = []

    server.use(
      ...referentials(calls),
      http.post(`${API}/vehicles`, () => {
        posted = true

        return HttpResponse.json({ data: vehicle(), meta: [] }, { status: 201 })
      }),
    )

    renderWithProviders(<VehicleCreatePage />, {
      membership: withPermissions(['vehicles.create']),
    })

    await fill()
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(await screen.findAllByText('Ce champ est obligatoire.')).not.toHaveLength(0)
    expect(posted).toBe(false)
  })
})
