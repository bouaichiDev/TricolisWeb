import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { DriverCreatePage } from './DriverCreatePage'
import { DriverListPage } from './DriverListPage'

const PROVIDER_ID = '01JQZ0000000000000PROV01'

const driver = (overrides: Record<string, unknown> = {}) => ({
  id: '01JQZ0000000000000DRIV01',
  organizationId: '01JQZ0000000000000000ORG1',
  providerId: PROVIDER_ID,
  addressId: null,
  contactId: null,
  code: 'DRV-01',
  name: 'Ali Ben Salah',
  status: 'active',
  providerName: 'Transports Atlas',
  ...overrides,
})

const provider = {
  id: PROVIDER_ID,
  organizationId: '01JQZ0000000000000000ORG1',
  addressId: null,
  contactId: null,
  code: 'TRANS-01',
  name: 'Transports Atlas',
  status: 'active',
}

const status = (code: string, label: string, rank: number) => ({
  id: `01JQZ000000000000STAD0${rank}`,
  source: 'driver',
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

const referentials = [
  http.get(`${API}/statuses`, () =>
    HttpResponse.json(paginated([status('active', 'Actif', 1), status('inactive', 'Inactif', 2)])),
  ),
  http.get(`${API}/providers`, () => HttpResponse.json(paginated([provider]))),
]

describe('liste des chauffeurs', () => {
  const render = (permissions: string[], rows: unknown[] = [driver()]) => {
    const calls: URL[] = []

    server.use(
      ...referentials,
      http.get(`${API}/drivers`, ({ request }) => {
        calls.push(new URL(request.url))

        return HttpResponse.json(paginated(rows))
      }),
    )

    renderWithProviders(<DriverListPage />, { membership: withPermissions(permissions) })

    return calls
  }

  it('montre le chauffeur avec son fournisseur et son statut', async () => {
    render(['drivers.view'])

    expect(await screen.findByText('Ali Ben Salah')).toBeInTheDocument()
    expect(screen.getByText('Transports Atlas')).toBeInTheDocument()
    expect(await screen.findByText('Actif')).toBeInTheDocument()
  })

  it('filtre par fournisseur', async () => {
    const calls = render(['drivers.view'])

    await screen.findByText('Ali Ben Salah')
    await userEvent.click(screen.getByRole('combobox', { name: /Fournisseur/ }))
    await userEvent.click(await screen.findByRole('option', { name: /Transports Atlas/ }))

    await waitFor(() =>
      expect(
        calls.some(
          (url) =>
            url.pathname.endsWith('/drivers') &&
            url.searchParams.get('providerId') === PROVIDER_ID,
        ),
      ).toBe(true),
    )
  })

  /** « Tous » ne doit pas partir comme un `providerId` vide dans l'URL. */
  it('ne filtre plus quand on revient à « Tous »', async () => {
    const calls = render(['drivers.view'])

    await screen.findByText('Ali Ben Salah')
    await userEvent.click(screen.getByRole('combobox', { name: /Fournisseur/ }))
    await userEvent.click(await screen.findByRole('option', { name: /Transports Atlas/ }))
    await userEvent.click(screen.getByRole('combobox', { name: /Fournisseur/ }))
    await userEvent.click(await screen.findByRole('option', { name: 'Tous' }))

    await waitFor(() => {
      const last = calls.filter((url) => url.pathname.endsWith('/drivers')).at(-1)
      expect(last?.searchParams.has('providerId')).toBe(false)
    })
  })

  it('masque la création sans la permission', async () => {
    render(['drivers.view'])

    await screen.findByText('Ali Ben Salah')

    expect(screen.queryByRole('link', { name: /Ajouter un chauffeur/ })).not.toBeInTheDocument()
  })
})

describe('création d’un chauffeur', () => {
  /**
   * Arriver depuis la fiche d'un fournisseur préremplit celui-ci : le
   * redemander alors qu'on vient de sa page serait absurde.
   */
  it('préremplit le fournisseur venu de l’URL', async () => {
    let body: unknown = null

    server.use(
      ...referentials,
      http.post(`${API}/drivers`, async ({ request }) => {
        body = await request.json()

        return HttpResponse.json({ data: driver(), meta: [] }, { status: 201 })
      }),
    )

    renderWithProviders(<DriverCreatePage />, {
      membership: withPermissions(['drivers.create']),
      route: `/drivers/create?providerId=${PROVIDER_ID}`,
      routePath: '/drivers/create',
    })

    expect(await screen.findByText('Transports Atlas')).toBeInTheDocument()

    await userEvent.type(screen.getByLabelText(/^Code/), 'DRV-NEW')
    await userEvent.type(screen.getByLabelText(/^Nom/), 'Karim Bensaïd')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({
      providerId: PROVIDER_ID,
      code: 'DRV-NEW',
      name: 'Karim Bensaïd',
      status: 'active',
    })
  })

  it('refuse d’enregistrer sans fournisseur', async () => {
    let posted = false

    server.use(
      ...referentials,
      http.post(`${API}/drivers`, () => {
        posted = true

        return HttpResponse.json({ data: driver(), meta: [] }, { status: 201 })
      }),
    )

    renderWithProviders(<DriverCreatePage />, {
      membership: withPermissions(['drivers.create']),
    })

    await userEvent.type(await screen.findByLabelText(/^Code/), 'DRV-NEW')
    await userEvent.type(screen.getByLabelText(/^Nom/), 'Karim Bensaïd')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(await screen.findAllByText('Ce champ est obligatoire.')).not.toHaveLength(0)
    expect(posted).toBe(false)
  })
})
