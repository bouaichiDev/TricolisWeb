import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { paginated, platformMembership, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { StatusPropagationsPanel } from './StatusPropagationsPanel'

const HIERARCHY = {
  order: ['order_service', 'package', 'order_line'],
  order_service: ['package'],
  package: ['order_line'],
}

const RULE = {
  id: 'R-1',
  fromStatusId: 'S-LOADED',
  toStatusId: 'S-PROGRESS',
  from: { source: 'package', code: 'loaded', label: 'Chargé' },
  to: { source: 'order_service', code: 'in_progress', label: 'En route' },
  mode: 'all',
  active: true,
}

const STATUSES = [
  {
    id: 'S-LOADED',
    source: 'package',
    status: 3,
    code: 'loaded',
    label: 'Chargé',
    icon: null,
    active: true,
    isToSend: false,
    allowsContentChanges: true,
    requiresReason: false,
    isDefault: false,
    position: 1,
    createdAt: '2026-08-01T09:00:00.000000Z',
    updatedAt: '2026-08-01T09:00:00.000000Z',
  },
]

function serve() {
  const posts: unknown[] = []
  const patches: unknown[] = []

  server.use(
    http.get(`${API}/status-propagations`, () =>
      HttpResponse.json({ data: { rules: [RULE], hierarchy: HIERARCHY }, meta: [] }),
    ),
    http.post(`${API}/status-propagations`, async ({ request }) => {
      posts.push(await request.json())
      return HttpResponse.json({ data: {}, meta: [] }, { status: 201 })
    }),
    http.patch(`${API}/status-propagations/:id`, async ({ request }) => {
      patches.push(await request.json())
      return HttpResponse.json({ data: RULE, meta: [] })
    }),
    http.get(`${API}/statuses`, () => HttpResponse.json(paginated(STATUSES))),
  )

  return { posts, patches }
}

const platform = platformMembership({
  permissions: [
    { id: 'p-1', code: 'statuses.update' },
    { id: 'p-2', code: 'statuses.create' },
  ],
})

describe('StatusPropagationsPanel', () => {
  /** Une règle se lit de gauche à droite, comme elle s'exécute. */
  it('lit la règle d’un trait', async () => {
    serve()
    renderWithProviders(<StatusPropagationsPanel />, {
      membership: platform,
    })

    expect(await screen.findByText('Colis « Chargé »')).toBeInTheDocument()
    expect(screen.getByText('Service de commande « En route »')).toBeInTheDocument()
  })

  /**
   * Le point central du formulaire : les entités proposées à droite sont celles
   * que la hiérarchie relie. Une règle commande → véhicule ne mènerait nulle part.
   */
  it('ne propose que des entités reliées, et demande la condition en remontant', async () => {
    serve()
    renderWithProviders(<StatusPropagationsPanel />, {
      membership: platform,
    })

    await userEvent.click(await screen.findByLabelText(/Quand — entité/))
    await userEvent.click(await screen.findByRole('option', { name: /Colis/ }))

    await userEvent.click(screen.getByLabelText(/Alors — entité/))

    // Le colis est relié au service (son contenant) et à la ligne (son contenu).
    expect(await screen.findByRole('option', { name: /Service/ })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: /Véhicule/ })).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('option', { name: /Service/ }))

    // La règle remonte : « tous les colis, ou un seul ? » se pose alors.
    expect(await screen.findByLabelText(/Condition/)).toBeInTheDocument()
  })

  it('déclare la règle choisie', async () => {
    const { posts } = serve()
    renderWithProviders(<StatusPropagationsPanel />, {
      membership: platform,
    })

    await userEvent.click(await screen.findByLabelText(/Quand — entité/))
    await userEvent.click(await screen.findByRole('option', { name: /Colis/ }))
    await userEvent.click(screen.getByLabelText(/Quand — statut/))
    await userEvent.click(await screen.findByRole('option', { name: /Chargé/ }))
    await userEvent.click(screen.getByLabelText(/Alors — entité/))
    await userEvent.click(await screen.findByRole('option', { name: /Ligne/ }))
    await userEvent.click(screen.getByLabelText(/Alors — statut/))
    await userEvent.click(await screen.findByRole('option', { name: /Chargé/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Ajouter' }))

    await waitFor(() =>
      expect(posts).toEqual([
        { fromStatusId: 'S-LOADED', toStatusId: 'S-LOADED', mode: 'all' },
      ]),
    )
  })

  /**
   * Une règle mal visée se répare : le formulaire reprend la règle choisie, et
   * l'enregistrement la corrige au lieu d'en créer une seconde.
   */
  it('corrige une règle au lieu d’en créer une autre', async () => {
    const { patches, posts } = serve()
    renderWithProviders(<StatusPropagationsPanel />, { membership: platform })

    await userEvent.click(await screen.findByRole('button', { name: 'Modifier la règle' }))

    expect(await screen.findByText('Modifier la règle')).toBeInTheDocument()

    await userEvent.click(screen.getByLabelText(/Condition/))
    await userEvent.click(await screen.findByRole('option', { name: 'Un seul suffit' }))
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() =>
      expect(patches).toEqual([
        { fromStatusId: 'S-LOADED', toStatusId: 'S-PROGRESS', mode: 'any' },
      ]),
    )
    expect(posts).toEqual([])
  })

  /** Le référentiel décrit le domaine : un administrateur d'organisme le lit. */
  it('n’offre ni écriture ni formulaire à un administrateur d’organisation', async () => {
    serve()
    renderWithProviders(<StatusPropagationsPanel />, {
      membership: withPermissions(['statuses.view']),
    })

    const rule = (await screen.findByText('Colis « Chargé »')).closest('li') as HTMLElement

    expect(within(rule).queryByRole('button')).not.toBeInTheDocument()
    expect(screen.queryByText('Nouvelle règle')).not.toBeInTheDocument()
  })
})
