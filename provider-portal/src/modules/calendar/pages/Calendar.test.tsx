import { screen } from '@testing-library/react'
import { http, HttpResponse } from 'msw'

import { ADMIN_PERMISSIONS, makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'], now: new Date(2026, 10, 3, 9) })
})

afterEach(() => {
  vi.useRealTimers()
})

describe('calendrier', () => {
  it('ne charge que la période visible et affiche les tournées', async () => {
    const urls: URL[] = []
    server.use(
      http.get(`${API}/tours/calendar`, ({ request }) => {
        urls.push(new URL(request.url))
        return HttpResponse.json({
          data: [{ id: '01TOUR1', title: 'T-ALPHA-1', start: '2026-11-03T07:00:00+01:00', end: '2026-11-03T12:00:00+01:00', allDay: false, status: 'confirmed' }],
          meta: {},
        })
      }),
    )

    renderApp('/calendar', makeContext([...ADMIN_PERMISSIONS, 'provider.calendar.view', 'provider.tours.view']))

    expect(await screen.findByText('T-ALPHA-1')).toBeInTheDocument()
    expect(urls[0].searchParams.get('from')).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    expect(screen.getByRole('button', { name: 'Semaine' })).toBeInTheDocument()
  })

  it('est fermé sans la permission du calendrier', async () => {
    renderApp('/calendar', makeContext(ADMIN_PERMISSIONS))

    expect(await screen.findByRole('heading', { name: 'Accès refusé' })).toBeInTheDocument()
  })
})
