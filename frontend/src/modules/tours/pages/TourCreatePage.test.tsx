import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { ORGANIZATION_ID, paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { TourCreatePage } from './TourCreatePage'

const AGENCY_ID = '01JQZ0000000000000AGEN01'
const DEPOT_ID = '01JQZ0000000000000DEPO01'
const DRIVER_ID = '01JQZ0000000000000DRIV01'
const TOUR_ID = '01JQZ0000000000000TOUR01'

function render() {
  const bodies: Record<string, unknown>[] = []

  server.use(
    http.get(`${API}/agencies`, () =>
      HttpResponse.json(paginated([{ id: AGENCY_ID, code: 'AG-01', name: 'Casablanca' }])),
    ),
    http.get(`${API}/agencies/${AGENCY_ID}/depots`, () =>
      HttpResponse.json(
        paginated([{ id: DEPOT_ID, agencyId: AGENCY_ID, code: 'DP-01', name: 'Entrepôt Sud' }]),
      ),
    ),
    http.get(`${API}/providers`, () => HttpResponse.json(paginated([]))),
    http.get(`${API}/drivers`, () =>
      HttpResponse.json(paginated([{ id: DRIVER_ID, code: 'CH-01', name: 'Youssef Alami' }])),
    ),
    http.get(`${API}/vehicles`, () => HttpResponse.json(paginated([]))),
    http.post(`${API}/tours`, async ({ request }) => {
      bodies.push((await request.json()) as Record<string, unknown>)

      return HttpResponse.json({ data: { id: TOUR_ID, organizationId: ORGANIZATION_ID } })
    }),
  )

  renderWithProviders(<TourCreatePage />, {
    membership: withPermissions(['tours.view', 'tours.create']),
  })

  return bodies
}

/** Choisit une option dans un `Select` Radix, désigné par son libellé. */
async function pick(label: string, option: string) {
  await userEvent.click(screen.getByRole('combobox', { name: new RegExp(label) }))
  await userEvent.click(await screen.findByRole('option', { name: new RegExp(option) }))
}

describe('création d’une tournée', () => {
  /**
   * Le dépôt appartient à l'agence : le proposer avant elle laisserait choisir
   * un dépôt d'une autre, que le serveur refuserait.
   */
  it('n’ouvre le dépôt qu’une fois l’agence choisie', async () => {
    render()

    expect(await screen.findByText('Choisissez d’abord une agence.')).toBeInTheDocument()

    await pick('Agence', 'Casablanca')

    await waitFor(() =>
      expect(screen.queryByText('Choisissez d’abord une agence.')).not.toBeInTheDocument(),
    )
  })

  /**
   * Une tournée naît au brouillon, et les moyens non affectés partent à `null` :
   * la sentinelle « aucun » ne doit jamais atteindre le serveur.
   */
  it('annonce que le numéro est attribué par le système', async () => {
    render()

    expect(
      await screen.findByText('Le numéro est attribué par le système à l’enregistrement.'),
    ).toBeInTheDocument()

    expect(screen.queryByLabelText(/Numéro/)).not.toBeInTheDocument()
  })

  it('envoie un brouillon, moyens non affectés à null', async () => {
    const bodies = render()

    await userEvent.type(await screen.findByLabelText(/^Date/), '2026-09-01')
    await pick('Agence', 'Casablanca')
    await pick('Chauffeur', 'Youssef Alami')

    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(bodies).toHaveLength(1))

    // Le numero n'est pas envoye : le serveur l'attribue, et le formulaire le
    // dit plutot que de laisser un champ vide sans explication.
    expect(bodies[0]).not.toHaveProperty('tourNumber')
    expect(bodies[0]).toMatchObject({
      tourDate: '2026-09-01',
      agencyId: AGENCY_ID,
      driverId: DRIVER_ID,
      status: 'draft',
      depotId: null,
      vehicleId: null,
      providerId: null,
    })
  })
})
