import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { ADMIN_PERMISSIONS, makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'
import { makeTour, tourPage } from '@/test/tourFixtures'

const EXECUTE = [...ADMIN_PERMISSIONS, 'provider.tours.view', 'provider.tours.execute', 'provider.tour-stops.update-status']
const running = (stopActions: Record<string, boolean>) => {
  const tour = makeTour({ status: 'in_progress', statusLabel: 'En cours', actions: { canAssign: false, canStart: false, canComplete: true } })
  tour.stops[0] = { ...tour.stops[0], actions: { canArrive: false, canComplete: false, canSkip: false, ...stopActions } } as (typeof tour.stops)[0]

  return tour
}

describe('exécution des tournées', () => {
  it('range les tournées en onglets côté serveur', async () => {
    const states: (string | null)[] = []
    server.use(
      http.get(`${API}/tours`, ({ request }) => {
        states.push(new URL(request.url).searchParams.get('state'))
        return HttpResponse.json(tourPage([makeTour()]))
      }),
    )
    renderApp('/tours', makeContext(EXECUTE))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('tab', { name: 'En cours' }))

    await waitFor(() => expect(states).toContain('running'))
    expect(states[0]).toBe('todo')
  })

  it('démarre une tournée quand le serveur l’ouvre', async () => {
    let started = false
    server.use(
      http.get(`${API}/tours/01TOUR1`, () => HttpResponse.json({ data: makeTour({ actions: { canAssign: false, canStart: true, canComplete: false } }), meta: {} })),
      http.post(`${API}/tours/01TOUR1/start`, () => {
        started = true
        return HttpResponse.json({ data: makeTour({ status: 'in_progress' }), meta: {} })
      }),
    )
    renderApp('/tours/01TOUR1', makeContext(EXECUTE))

    await userEvent.setup().click(await screen.findByRole('button', { name: 'Démarrer' }))

    await waitFor(() => expect(started).toBe(true))
  })

  it('enregistre l’arrivée à un arrêt', async () => {
    let arrived = false
    server.use(
      http.get(`${API}/tours/01TOUR1`, () => HttpResponse.json({ data: running({ canArrive: true, canSkip: true }), meta: {} })),
      http.post(`${API}/tours/01TOUR1/stops/01STOP1/arrive`, () => {
        arrived = true
        return HttpResponse.json({ data: running({}).stops[0], meta: {} })
      }),
    )
    renderApp('/tours/01TOUR1', makeContext(EXECUTE))

    await userEvent.setup().click(await screen.findByRole('button', { name: 'Arrivé' }))

    await waitFor(() => expect(arrived).toBe(true))
  })

  it('passe un arrêt avec un motif', async () => {
    let body: unknown = null
    server.use(
      http.get(`${API}/tours/01TOUR1`, () => HttpResponse.json({ data: running({ canArrive: true, canSkip: true }), meta: {} })),
      http.post(`${API}/tours/01TOUR1/stops/01STOP1/skip`, async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: running({}).stops[0], meta: {} })
      }),
    )
    renderApp('/tours/01TOUR1', makeContext(EXECUTE))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Passer' }))
    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText('Motif'), 'Client absent')
    await user.click(within(dialog).getByRole('button', { name: 'Passer l’arrêt' }))

    await waitFor(() => expect(body).toEqual({ reason: 'Client absent' }))
  })

  it('dit pourquoi le serveur refuse de terminer', async () => {
    server.use(
      http.get(`${API}/tours/01TOUR1`, () => HttpResponse.json({ data: running({}), meta: {} })),
      http.post(`${API}/tours/01TOUR1/complete`, () =>
        HttpResponse.json({ message: 'Invalide.', errors: { status: ['Des arrêts ne sont ni terminés ni passés : terminez-les d’abord.'] } }, { status: 422 }),
      ),
    )
    renderApp('/tours/01TOUR1', makeContext(EXECUTE))
    const user = userEvent.setup()

    await user.click(await screen.findByRole('button', { name: 'Terminer la tournée' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Terminer' }))

    expect(await screen.findByText('Des arrêts ne sont ni terminés ni passés : terminez-les d’abord.')).toBeInTheDocument()
  })
})
