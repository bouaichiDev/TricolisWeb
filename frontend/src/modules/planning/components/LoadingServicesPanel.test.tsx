import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { makeMembership, paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { LoadingServicesPanel } from './LoadingServicesPanel'

const ORGANIZATION_ID = makeMembership().id

const service = (code: string, name: string, suffix: string) => ({
  id: `01JQZ000000000000SERV${suffix}`,
  organizationId: ORGANIZATION_ID,
  code,
  name,
  status: 'active',
})

function render(loadingServiceCodes: string[] = [], services = [
  service('LOAD', 'Chargement', '01'),
  service('DELIV', 'Livraison', '02'),
]) {
  const sent: unknown[] = []

  server.use(
    http.get(`${API}/organizations/${ORGANIZATION_ID}`, () =>
      HttpResponse.json({
        data: {
          id: ORGANIZATION_ID,
          code: 'atlas',
          name: 'Atlas Transport',
          status: 'active',
          settings: { planning: { loadingServiceCodes } },
        },
        meta: [],
      }),
    ),
    http.get(`${API}/services`, () => HttpResponse.json(paginated(services))),
    http.patch(`${API}/organizations/${ORGANIZATION_ID}`, async ({ request }) => {
      sent.push(await request.json())

      return HttpResponse.json({ data: { id: ORGANIZATION_ID }, meta: [] })
    }),
  )

  renderWithProviders(<LoadingServicesPanel />, {
    membership: withPermissions(['organizations.update', 'services.view']),
  })

  return sent
}

/**
 * Le serveur reconnaît un chargement à son code. Cet écran coche les services
 * réels plutôt que de faire saisir le code : une faute de frappe ne serait
 * signalée par rien avant que le regroupement au dépôt ne s'arrête.
 */
describe('services de chargement', () => {
  it('coche les services déjà réglés', async () => {
    render(['LOAD'])

    const loading = await screen.findByRole('checkbox', { name: /Chargement/ })
    expect(loading).toBeChecked()
    expect(screen.getByRole('checkbox', { name: /Livraison/ })).not.toBeChecked()
  })

  it('envoie les codes cochés, en majuscules', async () => {
    const sent = render([])

    await userEvent.click(await screen.findByRole('checkbox', { name: /Chargement/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(sent).toHaveLength(1))
    expect(sent[0]).toMatchObject({ settings: { planning: { loadingServiceCodes: ['LOAD'] } } })
  })

  /**
   * `PATCH` remplace `settings` en entier : n'envoyer que la planification
   * effacerait tout le reste des réglages de l'organisation.
   */
  it('conserve les autres réglages', async () => {
    const sent: unknown[] = []

    server.use(
      http.get(`${API}/organizations/${ORGANIZATION_ID}`, () =>
        HttpResponse.json({
          data: {
            id: ORGANIZATION_ID,
            code: 'atlas',
            name: 'Atlas Transport',
            status: 'active',
            settings: { invoicing: { prefix: 'FA' }, planning: { loadingServiceCodes: [] } },
          },
          meta: [],
        }),
      ),
      http.get(`${API}/services`, () =>
        HttpResponse.json(paginated([service('LOAD', 'Chargement', '01')])),
      ),
      http.patch(`${API}/organizations/${ORGANIZATION_ID}`, async ({ request }) => {
        sent.push(await request.json())

        return HttpResponse.json({ data: { id: ORGANIZATION_ID }, meta: [] })
      }),
    )

    renderWithProviders(<LoadingServicesPanel />, {
      membership: withPermissions(['organizations.update', 'services.view']),
    })

    await userEvent.click(await screen.findByRole('checkbox', { name: /Chargement/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(sent).toHaveLength(1))
    expect(sent[0]).toMatchObject({ settings: { invoicing: { prefix: 'FA' } } })
  })

  /** Un service renommé après coup laisse un code orphelin : il doit se voir. */
  it('signale un code réglé qui ne correspond à aucun service', async () => {
    render(['CHARG'], [service('LOAD', 'Chargement', '01')])

    expect(await screen.findByText(/CHARG/)).toBeInTheDocument()
    expect(screen.getByText(/renomm/)).toBeInTheDocument()
  })

  it('dit quand aucun service n’est déclaré', async () => {
    render([], [])

    expect(await screen.findByText(/Aucun service/)).toBeInTheDocument()
  })
})
