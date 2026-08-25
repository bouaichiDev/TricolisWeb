import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { TypeListPage } from './TypeListPage'

const VEHICLE_ID = '01JQZ0000000000000TYPE01'
const COLOR_ID = '01JQZ0000000000000TYPE02'

const source = (overrides: Record<string, unknown> = {}) => ({
  id: VEHICLE_ID,
  organizationId: '01JQZ0000000000000000ORG1',
  code: 'vehicle',
  name: 'Type de véhicule',
  status: 'active',
  isSystem: true,
  itemCount: 2,
  createdAt: '2026-08-01T09:00:00+00:00',
  updatedAt: '2026-08-01T09:00:00+00:00',
  ...overrides,
})

const item = (overrides: Record<string, unknown> = {}) => ({
  id: '01JQZ0000000000000ITEM01',
  organizationId: '01JQZ0000000000000000ORG1',
  typeId: VEHICLE_ID,
  typeCode: 'vehicle',
  code: 'CAM19',
  name: 'Camion 19T',
  status: 'active',
  position: 0,
  createdAt: '2026-08-01T09:00:00+00:00',
  updatedAt: '2026-08-01T09:00:00+00:00',
  ...overrides,
})

function render(permissions: string[], sources = [source(), source({ id: COLOR_ID, code: 'couleur', name: 'Couleur', isSystem: false, itemCount: 0 })]) {
  const calls: URL[] = []

  server.use(
    http.get(`${API}/types`, () => HttpResponse.json(paginated(sources))),
    http.get(`${API}/type-items`, ({ request }) => {
      calls.push(new URL(request.url))

      return HttpResponse.json(paginated([item()]))
    }),
  )

  renderWithProviders(<TypeListPage />, { membership: withPermissions(permissions) })

  return calls
}

describe('référentiels de type', () => {
  it('montre les sources et les valeurs de la première', async () => {
    const calls = render(['types.view'])

    expect(await screen.findByText('Type de véhicule')).toBeInTheDocument()
    expect(screen.getByText('Couleur')).toBeInTheDocument()
    expect(await screen.findByText('Camion 19T')).toBeInTheDocument()

    // Les valeurs sont demandées pour la source retenue, pas toutes à la fois.
    await waitFor(() => expect(calls).not.toHaveLength(0))
    expect(calls[0].searchParams.get('typeId')).toBe(VEHICLE_ID)
  })

  it('change de source au clic', async () => {
    const calls = render(['types.view'])

    await userEvent.click(await screen.findByRole('button', { name: /Couleur/ }))

    await waitFor(() =>
      expect(calls.some((url) => url.searchParams.get('typeId') === COLOR_ID)).toBe(true),
    )
  })

  /**
   * Le schéma désigne les sources structurelles par leur code : le laisser
   * saisir promettrait une modification que le serveur refuse.
   */
  it('verrouille le code d’une source structurelle, pas celui des autres', async () => {
    render(['types.view', 'types.update'])

    await screen.findByText('Type de véhicule')
    await userEvent.click(screen.getByRole('button', { name: 'Modifier Type de véhicule' }))

    expect(within(await screen.findByRole('dialog')).getByLabelText(/^Code/)).toBeDisabled()

    await userEvent.click(screen.getByRole('button', { name: 'Annuler' }))

    await userEvent.click(screen.getByRole('button', { name: 'Modifier Couleur' }))
    await waitFor(() =>
      expect(within(screen.getByRole('dialog')).getByLabelText(/^Code/)).toBeEnabled(),
    )
  })

  /** Une source structurelle ne se supprime pas : la colonne qui la désigne resterait sans cible. */
  it('n’offre pas de supprimer une source structurelle', async () => {
    render(['types.view', 'types.delete'])

    await screen.findByText('Type de véhicule')

    expect(
      screen.queryByRole('button', { name: 'Supprimer Type de véhicule' }),
    ).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Supprimer Couleur' })).toBeInTheDocument()
  })

  it('crée une valeur dans la source retenue', async () => {
    let body: unknown = null
    render(['types.view', 'types.create'])

    server.use(
      http.post(`${API}/type-items`, async ({ request }) => {
        body = await request.json()

        return HttpResponse.json({ data: item(), meta: [] }, { status: 201 })
      }),
    )

    await screen.findByText('Camion 19T')
    await userEvent.click(screen.getByRole('button', { name: /Ajouter une valeur/ }))

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.type(dialog.getByLabelText(/^Code/), 'VL')
    await userEvent.type(dialog.getByLabelText(/^Nom/), 'Véhicule léger')
    await userEvent.click(dialog.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).toMatchObject({ typeId: VEHICLE_ID, code: 'VL', name: 'Véhicule léger' })
  })

  it('masque création, modification et suppression sans les permissions', async () => {
    render(['types.view'])

    await screen.findByText('Type de véhicule')

    expect(screen.queryByRole('button', { name: /Ajouter un type/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Ajouter une valeur/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Modifier Couleur' })).not.toBeInTheDocument()
  })
})
