import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { makeMembership } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { AutoLoadingServicePanel } from './AutoLoadingServicePanel'

const ORGANIZATION_ID = makeMembership().id

function render(settings: Record<string, unknown> = {}) {
  const sent: Record<string, unknown>[] = []

  server.use(
    http.get(`${API}/organizations/${ORGANIZATION_ID}`, () =>
      HttpResponse.json({
        data: {
          id: ORGANIZATION_ID,
          code: 'atlas',
          name: 'Atlas Transport',
          status: 'active',
          settings,
        },
        meta: [],
      }),
    ),
    http.patch(`${API}/organizations/${ORGANIZATION_ID}`, async ({ request }) => {
      sent.push((await request.json()) as Record<string, unknown>)

      return HttpResponse.json({ data: { id: ORGANIZATION_ID }, meta: [] })
    }),
  )

  renderWithProviders(<AutoLoadingServicePanel />)

  return sent
}

const toggle = () => screen.findByRole('switch', { name: 'Créer le chargement manquant' })

describe('création automatique du chargement', () => {
  /**
   * Une organisation qui n'a jamais réglé la question ne doit pas voir des
   * prestations apparaître dans ses commandes.
   */
  it('est coupée tant que rien n’est réglé', async () => {
    render()

    expect(await toggle()).not.toBeChecked()
  })

  it('reflète le réglage enregistré', async () => {
    render({ planning: { autoCreateLoadingService: true } })

    await waitFor(async () => expect(await toggle()).toBeChecked())
  })

  it('enregistre au basculement, sans bouton', async () => {
    const sent = render()

    await userEvent.click(await toggle())

    await waitFor(() => expect(sent).toHaveLength(1))
    expect(sent[0]).toMatchObject({
      settings: { planning: { autoCreateLoadingService: true } },
    })
  })

  /**
   * `PATCH` remplace `settings` en entier : n'envoyer que cette option
   * effacerait les codes de chargement et tout le reste.
   */
  it('n’efface pas les autres réglages', async () => {
    const sent = render({ planning: { loadingServiceCodes: ['LOAD'] }, menu: { hidden: ['x'] } })

    await userEvent.click(await toggle())

    await waitFor(() => expect(sent).toHaveLength(1))
    expect(sent[0]).toMatchObject({
      settings: {
        planning: { loadingServiceCodes: ['LOAD'], autoCreateLoadingService: true },
        menu: { hidden: ['x'] },
      },
    })
  })

  /** Ce que l'option coûte quand elle ne peut pas tenir sa promesse. */
  it('annonce le refus qu’elle entraîne sans dépôt', async () => {
    render()

    expect(await screen.findByText(/Une tournée sans dépôt refuse alors/)).toBeInTheDocument()
  })
})
