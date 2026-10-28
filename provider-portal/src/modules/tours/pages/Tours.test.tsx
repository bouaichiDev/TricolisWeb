import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { ADMIN_PERMISSIONS, makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'
import { makeTour, tourPage } from '@/test/tourFixtures'

const DISPATCH = [...ADMIN_PERMISSIONS, 'provider.tours.view', 'provider.tours.assign-resources', 'provider.drivers.view', 'provider.vehicles.view']
const driver = { id: '01DRV1', code: 'ALPHA-D1', name: 'Adam Alpha', status: 'active' }
const vehicle = { id: '01VEH1', code: 'ALPHA-V1', registrationNumber: 'GE-1', status: 'active' }

function serveFleet() {
  server.use(
    http.get(`${API}/drivers`, () => HttpResponse.json(tourPage([driver]))),
    http.get(`${API}/vehicles`, () => HttpResponse.json(tourPage([vehicle]))),
  )
}

describe('tournées', () => {
  it('liste les tournées attribuées et filtre par statut côté serveur', async () => {
    const statuses: (string | null)[] = []
    server.use(
      http.get(`${API}/tours`, ({ request }) => {
        statuses.push(new URL(request.url).searchParams.get('status'))
        return HttpResponse.json(tourPage([makeTour()]))
      }),
    )
    renderApp('/tours', makeContext(DISPATCH))

    expect(await screen.findByRole('link', { name: 'T-ALPHA-1' })).toHaveAttribute('href', '/tours/01TOUR1')
    expect(screen.getByText('Confirmée')).toBeInTheDocument()
  })

  it('montre le détail : arrêts dans l’ordre, services à réaliser, contacts sur place', async () => {
    server.use(http.get(`${API}/tours/01TOUR1`, () => HttpResponse.json({ data: makeTour(), meta: {} })))
    renderApp('/tours/01TOUR1', makeContext(DISPATCH))

    expect(await screen.findByRole('heading', { name: /T-ALPHA-1/ })).toBeInTheDocument()
    expect(screen.getByText('Rue du Port 4 · 1201 Genève')).toBeInTheDocument()
    expect(screen.getByText('SRV-00042')).toBeInTheDocument()
    expect(screen.getByText(/Lea Site/)).toBeInTheDocument()
  })

  it('affecte un chauffeur et un véhicule de sa flotte', async () => {
    let body: unknown = null
    serveFleet()
    server.use(
      http.get(`${API}/tours/01TOUR1`, () => HttpResponse.json({ data: makeTour(), meta: {} })),
      http.patch(`${API}/tours/01TOUR1/resources`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: makeTour({ driver: { id: '01DRV1', code: 'ALPHA-D1', name: 'Adam Alpha' } }), meta: {} })
      }),
    )
    renderApp('/tours/01TOUR1', makeContext(DISPATCH))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Affecter' }))
    const dialog = await screen.findByRole('dialog')
    await user.selectOptions(within(dialog).getByLabelText('Chauffeur'), await within(dialog).findByRole('option', { name: /Adam Alpha/ }))
    await user.selectOptions(within(dialog).getByLabelText('Véhicule'), await within(dialog).findByRole('option', { name: /ALPHA-V1/ }))
    await user.click(within(dialog).getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).toEqual({ driverId: '01DRV1', vehicleId: '01VEH1' }))
  })

  it('n’envoie que ce qui change, et garde les arrêts affichés après l’affectation', async () => {
    let body: unknown = null
    let detailCalls = 0
    serveFleet()
    const assigned = { id: '01DRV1', code: 'ALPHA-D1', name: 'Adam Alpha' }
    server.use(
      http.get(`${API}/tours/01TOUR1`, () => {
        detailCalls += 1
        return HttpResponse.json({ data: makeTour({ driver: assigned }), meta: {} })
      }),
      http.patch(`${API}/tours/01TOUR1/resources`, async ({ request }) => {
        body = await request.json()
        const { stops: _stops, ...summary } = makeTour({ driver: assigned })
        return HttpResponse.json({ data: summary, meta: {} })
      }),
    )
    renderApp('/tours/01TOUR1', makeContext(DISPATCH))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Affecter' }))
    const dialog = await screen.findByRole('dialog')
    await user.selectOptions(within(dialog).getByLabelText('Véhicule'), await within(dialog).findByRole('option', { name: /ALPHA-V1/ }))
    await user.click(within(dialog).getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(body).toEqual({ vehicleId: '01VEH1' }))
    await waitFor(() => expect(detailCalls).toBeGreaterThan(1))
    expect(await screen.findByText('SRV-00042')).toBeInTheDocument()
  })

  it('montre la raison d’un refus d’affectation', async () => {
    serveFleet()
    server.use(
      http.get(`${API}/tours/01TOUR1`, () => HttpResponse.json({ data: makeTour(), meta: {} })),
      http.patch(`${API}/tours/01TOUR1/resources`, () =>
        HttpResponse.json({ message: 'Invalide.', errors: { driverId: ['Ce chauffeur est déjà affecté à une tournée sur ce créneau.'] } }, { status: 422 }),
      ),
    )
    renderApp('/tours/01TOUR1', makeContext(DISPATCH))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Affecter' }))
    const dialog = await screen.findByRole('dialog')
    await user.selectOptions(within(dialog).getByLabelText('Chauffeur'), await within(dialog).findByRole('option', { name: /Adam Alpha/ }))
    await user.click(within(dialog).getByRole('button', { name: 'Enregistrer' }))

    expect(await within(dialog).findByText('Ce chauffeur est déjà affecté à une tournée sur ce créneau.')).toBeInTheDocument()
  })

  it('n’offre pas l’affectation quand le serveur ne l’ouvre pas', async () => {
    server.use(http.get(`${API}/tours/01TOUR1`, () => HttpResponse.json({ data: makeTour({ actions: { canAssign: false } }), meta: {} })))
    renderApp('/tours/01TOUR1', makeContext(DISPATCH))

    expect(await screen.findByRole('heading', { name: /T-ALPHA-1/ })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Affecter' })).not.toBeInTheDocument()
  })
})
