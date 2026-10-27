import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'

import { makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'
import { makeCalendarEvent } from '@/test/trackingFixtures'

const PERMISSIONS = ['customer.calendar.view', 'customer.orders.view', 'customer.tracking.view', 'customer.orders.track']
const filters = { statuses: [{ code: 'planned', label: 'Planifiée' }], services: [{ id: '01SERVICE', code: 'DELIVERY', name: 'Livraison' }] }

function serveCalendar(onRequest: (url: URL) => void = () => undefined, event = makeCalendarEvent()) {
  server.use(
    http.get(`${API}/calendar/orders`, ({ request }) => {
      onRequest(new URL(request.url))
      return HttpResponse.json({ data: [event], meta: { from: '2026-08-31', to: '2026-10-12', count: 1, truncated: false, filters } })
    }),
  )
}

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'], now: new Date(2026, 8, 10, 12) })
})

afterEach(() => {
  vi.useRealTimers()
})

describe('calendar page', () => {
  it('loads only the visible period and shows the orders', async () => {
    const urls: URL[] = []
    serveCalendar((url) => urls.push(url))

    renderApp('/calendar', makeContext(PERMISSIONS))

    expect(await screen.findByText('IKEA-2026-000001')).toBeInTheDocument()
    expect(urls[0].searchParams.get('from')).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    expect(urls[0].searchParams.get('to')).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    expect(screen.getByRole('button', { name: 'Mois' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Semaine' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Jour' })).toBeInTheDocument()
  })

  it('shows the status of each order on the event, with its color, and a legend', async () => {
    serveCalendar()
    renderApp('/calendar', makeContext(PERMISSIONS))

    const title = await screen.findByText('IKEA-2026-000001')
    const event = title.closest('[data-status]') as HTMLElement

    expect(event).toHaveAttribute('data-status', 'planned')
    expect(event.className).toContain('text-primary')
    expect(within(event).getByText('Planifiée')).toBeInTheDocument()
    expect(within(screen.getByLabelText('Légende des statuts')).getByText('Planifiée')).toBeInTheDocument()
  })

  it('prefers the color set in the status referential', async () => {
    const event = makeCalendarEvent()
    event.extendedProps.status = { ...event.extendedProps.status, color: '#9333EA' }
    serveCalendar(undefined, event)
    renderApp('/calendar', makeContext(PERMISSIONS))

    const block = (await screen.findByText('IKEA-2026-000001')).closest('[data-status]') as HTMLElement

    expect(block.style.color).toBe('rgb(147, 51, 234)')
  })

  it('opens a quick view with the actions the backend allows', async () => {
    serveCalendar()
    renderApp('/calendar', makeContext(PERMISSIONS))
    const user = userEvent.setup()

    await user.click(await screen.findByText('IKEA-2026-000001'))

    const sheet = await screen.findByRole('dialog')
    expect(within(sheet).getByText('Votre référence : IKEA-4587')).toBeInTheDocument()
    expect(within(sheet).getByRole('link', { name: /Voir la commande/ })).toHaveAttribute('href', '/orders/01ORDER')
    expect(within(sheet).getByRole('link', { name: /Suivre/ })).toHaveAttribute('href', '/orders/01ORDER/tracking')
    expect(within(sheet).queryByRole('link', { name: /Modifier/ })).not.toBeInTheDocument()
  })

  it('sends the chosen filters to the backend', async () => {
    const urls: URL[] = []
    serveCalendar((url) => urls.push(url))
    renderApp('/calendar?status=planned', makeContext(PERMISSIONS))

    await screen.findByText('IKEA-2026-000001')

    await waitFor(() => expect(urls.at(-1)?.searchParams.getAll('status[]')).toEqual(['planned']))
  })

  it('is closed without the calendar permission', async () => {
    renderApp('/calendar', makeContext(['customer.orders.view']))

    expect(await screen.findByRole('heading', { name: 'Accès refusé' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Calendrier' })).not.toBeInTheDocument()
  })
})
