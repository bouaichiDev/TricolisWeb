import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'

import { ADMIN_PERMISSIONS, makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'

describe('DashboardPage', () => {
  it('affiche les cinq indicateurs servis par le serveur, sans en calculer aucun', async () => {
    server.use(
      http.get(`${API}/dashboard`, () =>
        HttpResponse.json({
          data: { toursToday: 4, toursInProgress: 2, toursCompletedToday: 1, activeDrivers: 7, activeVehicles: 3 },
          meta: {},
        }),
      ),
    )

    renderApp('/dashboard', makeContext(ADMIN_PERMISSIONS))

    const value = async (label: string) => within((await screen.findByText(label)).closest('[data-slot="card"]') as HTMLElement)

    expect((await value('Tournées du jour')).getByText('4')).toBeInTheDocument()
    expect((await value('Tournées en cours')).getByText('2')).toBeInTheDocument()
    expect((await value('Terminées aujourd’hui')).getByText('1')).toBeInTheDocument()
    expect((await value('Chauffeurs actifs')).getByText('7')).toBeInTheDocument()
    expect((await value('Véhicules actifs')).getByText('3')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: /Bonjour Alice/ })).toBeInTheDocument()
  })

  it('n’interroge pas les indicateurs sans `provider.dashboard.view`', async () => {
    let called = false
    server.use(
      http.get(`${API}/dashboard`, () => {
        called = true
        return HttpResponse.json({ message: 'Interdit.' }, { status: 403 })
      }),
    )

    renderApp('/dashboard', makeContext(['provider.portal.access', 'provider.profile.view']))

    expect(await screen.findByRole('heading', { name: /Bonjour Alice/ })).toBeInTheDocument()
    expect(screen.queryByText('Tournées du jour')).not.toBeInTheDocument()
    expect(called).toBe(false)
  })
})
