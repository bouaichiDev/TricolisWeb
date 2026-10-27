import { screen } from '@testing-library/react'
import { http, HttpResponse } from 'msw'

import { renderApp } from '@/test/renderApp'
import { server } from '@/test/server'
import { makePublicTracking } from '@/test/trackingFixtures'

vi.mock('@/shared/components/map/LazyTrackingMap', () => ({
  LazyTrackingMap: () => <div data-testid="tracking-map" />,
}))

const TOKEN = 'a'.repeat(64)
const PUBLIC_API = `http://localhost:8000/api/v1/public/tracking/${TOKEN}`

describe('public tracking page', () => {
  it('shows the tracking without any session', async () => {
    let authorization: string | null = 'unset'
    server.use(
      http.get(PUBLIC_API, ({ request }) => {
        authorization = request.headers.get('Authorization')
        return HttpResponse.json({ data: makePublicTracking(), meta: {} })
      }),
    )

    renderApp(`/t/${TOKEN}`)

    expect(await screen.findByRole('heading', { name: 'Commande IKEA-4587' })).toBeInTheDocument()
    expect(screen.getByText('Tricolis Dev')).toBeInTheDocument()
    expect(screen.getByTestId('eta-value')).toBeInTheDocument()
    expect(screen.getByText('Commande créée')).toBeInTheDocument()
    expect(screen.getByText(/Lausanne/)).toBeInTheDocument()
    expect(screen.queryByTestId('tracking-map')).not.toBeInTheDocument()
    expect(screen.queryByRole('navigation')).not.toBeInTheDocument()
    expect(authorization).toBeNull()
  })

  it('hides every block the carrier closed', async () => {
    server.use(
      http.get(PUBLIC_API, () =>
        HttpResponse.json({ data: makePublicTracking({ reference: null, timeline: null, eta: null, services: null, map: { available: true, currentPosition: { latitude: 46.5, longitude: 6.6, recordedAt: null }, points: [] } }), meta: {} }),
      ),
    )

    renderApp(`/t/${TOKEN}`)

    expect(await screen.findByTestId('tracking-map')).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: /Commande/ })).not.toBeInTheDocument()
    expect(screen.queryByText('Commande créée')).not.toBeInTheDocument()
    expect(screen.queryByText('Arrivée estimée')).not.toBeInTheDocument()
  })

  it('answers the same message for any unusable link', async () => {
    server.use(http.get(PUBLIC_API, () => HttpResponse.json({ message: 'Tracking indisponible', code: 'TRACKING_UNAVAILABLE' }, { status: 404 })))

    renderApp(`/t/${TOKEN}`)

    expect(await screen.findByText('Tracking indisponible')).toBeInTheDocument()
    expect(screen.getByText(/n’est pas valable ou n’est plus actif/)).toBeInTheDocument()
  })
})
