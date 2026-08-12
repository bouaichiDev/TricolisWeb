import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { CustomerListPage } from './CustomerListPage'
import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

const customer = {
  id: '01JQZ000000000000000CUST',
  organizationId: '01JQZ0000000000000000ORG1',
  code: 'CLI001',
  name: 'Client Alpha',
  legalName: null,
  email: 'contact@alpha.test',
  phone: null,
  paymentMode: null,
  communicationMode: null,
  catalogEnabled: false,
  stockEnabled: false,
  packageEnabled: false,
  appointmentEnabled: false,
  trackingEnabled: false,
  status: 'active',
  createdAt: '2026-02-01T10:00:00.000000Z',
  updatedAt: '2026-02-01T10:00:00.000000Z',
}

/** Capture les paramètres réellement envoyés : c'est eux qui font le filtrage. */
function captureQueries(rows: unknown[] = [customer]) {
  const queries: URLSearchParams[] = []

  server.use(
    http.get(`${API}/customers`, ({ request }) => {
      queries.push(new URL(request.url).searchParams)
      return HttpResponse.json(paginated(rows, { total: rows.length, lastPage: 2 }))
    }),
  )

  return queries
}

describe('CustomerListPage', () => {
  it('affiche les clients renvoyés par l’API', async () => {
    captureQueries()
    renderWithProviders(<CustomerListPage />, {
      membership: withPermissions(['customers.view']),
    })

    expect(await screen.findByText('Client Alpha')).toBeInTheDocument()
    expect(screen.getByText('CLI001')).toBeInTheDocument()
  })

  /**
   * Le filtrage est **serveur** : la page ne trie ni ne filtre localement, elle
   * renvoie les critères. Ce test vérifie que la recherche part bien dans
   * l'URL — sinon la liste paraîtrait filtrée alors qu'elle ne le serait pas.
   */
  it('transmet la recherche à l’API et revient en première page', async () => {
    const queries = captureQueries()
    renderWithProviders(<CustomerListPage />, {
      membership: withPermissions(['customers.view']),
    })

    await screen.findByText('Client Alpha')
    await userEvent.type(screen.getByRole('textbox', { name: 'Rechercher' }), 'alpha')

    // La saisie est différée de 350 ms : une frappe par requête serait du
    // gaspillage, et les réponses pourraient revenir dans le désordre.
    await waitFor(
      () => {
        expect(queries.some((query) => query.get('search') === 'alpha')).toBe(true)
      },
      { timeout: 2000 },
    )

    const last = queries[queries.length - 1]
    expect(last.get('page')).toBe('1')
  })

  it('transmet le tri demandé plutôt que de réordonner la page', async () => {
    const queries = captureQueries()
    renderWithProviders(<CustomerListPage />, {
      membership: withPermissions(['customers.view']),
    })

    await screen.findByText('Client Alpha')
    await userEvent.click(screen.getAllByRole('button', { name: /trier/i })[0])

    await waitFor(() => {
      const last = queries[queries.length - 1]
      expect(last.get('sort')).toBeTruthy()
      expect(last.get('direction')).toBeTruthy()
    })
  })

  it('masque la création sans customers.create', async () => {
    captureQueries()
    renderWithProviders(<CustomerListPage />, {
      membership: withPermissions(['customers.view']),
    })

    await screen.findByText('Client Alpha')
    expect(screen.queryByRole('link', { name: /nouveau client/i })).not.toBeInTheDocument()
  })

  it('propose la création avec customers.create', async () => {
    captureQueries()
    renderWithProviders(<CustomerListPage />, {
      membership: withPermissions(['customers.view', 'customers.create']),
    })

    await screen.findByText('Client Alpha')
    expect(screen.getByRole('link', { name: /nouveau client/i })).toBeInTheDocument()
  })

  it('affiche l’erreur du serveur au lieu d’une liste vide', async () => {
    server.use(
      http.get(`${API}/customers`, () =>
        HttpResponse.json({ message: 'Service indisponible.' }, { status: 500 }),
      ),
    )
    renderWithProviders(<CustomerListPage />, {
      membership: withPermissions(['customers.view']),
    })

    expect(await screen.findByText('Le chargement a échoué')).toBeInTheDocument()
  })
})
