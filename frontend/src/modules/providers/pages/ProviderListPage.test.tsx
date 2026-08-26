import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { ProviderListPage } from './ProviderListPage'

const PROVIDER_ID = '01JQZ0000000000000PROV01'

const provider = (overrides: Record<string, unknown> = {}) => ({
  id: PROVIDER_ID,
  organizationId: '01JQZ0000000000000000ORG1',
  addressId: null,
  contactId: null,
  code: 'TRANS-01',
  name: 'Transports Atlas',
  status: 'active',
  driverCount: 3,
  vehicleCount: 5,
  ...overrides,
})

/** Statuts du référentiel, tels que `GET /statuses?source=provider` les rend. */
const status = (code: string, label: string, rank: number) => ({
  id: `01JQZ000000000000STAT0${rank}`,
  source: 'provider',
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

function render(permissions: string[], rows: unknown[] = [provider()]) {
  const calls: URL[] = []

  server.use(
    http.get(`${API}/statuses`, () =>
      HttpResponse.json(
        paginated([status('active', 'Actif', 1), status('blocked', 'Bloqué', 2)]),
      ),
    ),
    http.get(`${API}/providers`, ({ request }) => {
      calls.push(new URL(request.url))

      return HttpResponse.json(paginated(rows))
    }),
  )

  renderWithProviders(<ProviderListPage />, { membership: withPermissions(permissions) })

  return calls
}

describe('liste des fournisseurs', () => {
  it('liste les fournisseurs avec leurs comptes et leur statut', async () => {
    render(['providers.view'])

    expect(await screen.findByText('Transports Atlas')).toBeInTheDocument()
    expect(screen.getByText('TRANS-01')).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()

    // Le libelle vient du referentiel, pas d'une cle i18n codee dans le module.
    expect(await screen.findByText('Actif')).toBeInTheDocument()
  })

  /**
   * Le filtre de statut est alimenté par `statuses` : un code ajouté par un
   * administrateur devient filtrable sans toucher au frontend.
   */
  it('filtre par un statut venu du référentiel', async () => {
    const calls = render(['providers.view'])

    await screen.findByText('Transports Atlas')
    await userEvent.click(screen.getByRole('combobox', { name: 'Statut' }))
    await userEvent.click(await screen.findByRole('option', { name: 'Bloqué' }))

    await waitFor(() =>
      expect(calls.some((url) => url.searchParams.get('status') === 'blocked')).toBe(true),
    )
  })

  it('recherche côté serveur', async () => {
    const calls = render(['providers.view'])

    await screen.findByText('Transports Atlas')
    await userEvent.type(screen.getByLabelText('Rechercher'), 'atlas')

    await waitFor(() =>
      expect(calls.some((url) => url.searchParams.get('search') === 'atlas')).toBe(true),
    )
  })

  it('masque la création sans la permission', async () => {
    render(['providers.view'])

    await screen.findByText('Transports Atlas')

    expect(screen.queryByRole('link', { name: /Ajouter un fournisseur/ })).not.toBeInTheDocument()
  })

  it('offre la création avec la permission', async () => {
    render(['providers.view', 'providers.create'])

    expect(await screen.findByRole('link', { name: /Ajouter un fournisseur/ })).toHaveAttribute(
      'href',
      '/providers/create',
    )
  })

  it('mène à la fiche par le code', async () => {
    render(['providers.view'])

    const row = await screen.findByRole('link', { name: 'TRANS-01' })
    expect(row).toHaveAttribute('href', `/providers/${PROVIDER_ID}`)
  })

  /** Une organisation sans fournisseur le dit, plutôt qu'un tableau vide muet. */
  it('annonce une liste vide', async () => {
    render(['providers.view'], [])

    expect(await screen.findByText('Aucun fournisseur.')).toBeInTheDocument()
    expect(within(screen.getByRole('table')).queryByText('TRANS-01')).not.toBeInTheDocument()
  })
})
