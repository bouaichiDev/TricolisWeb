import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'

import { ADMIN_PERMISSIONS, makeContext } from '@/test/fixtures'
import { renderApp } from '@/test/renderApp'
import { API, server } from '@/test/server'

describe('route and menu guards', () => {
  it('sends a visitor without session to the login page', async () => {
    renderApp('/dashboard')

    expect(await screen.findByRole('heading', { name: /Connexion/ })).toBeInTheDocument()
  })

  it('shows only the menu entries the member is allowed to use', async () => {
    renderApp('/dashboard', makeContext(['customer.orders.view']))

    const nav = await screen.findByRole('navigation', { name: 'Navigation principale' })

    expect(within(nav).getByText('Commandes')).toBeInTheDocument()
    expect(within(nav).queryByText('Utilisateurs')).not.toBeInTheDocument()
    expect(within(nav).queryByText('Stock')).not.toBeInTheDocument()
  })

  it('shows the users entry to an administrator', async () => {
    renderApp('/dashboard', makeContext(ADMIN_PERMISSIONS))

    const nav = await screen.findByRole('navigation', { name: 'Navigation principale' })

    expect(within(nav).getByRole('link', { name: /Utilisateurs/ })).toBeInTheDocument()
  })

  it('redirects to access denied when the permission is missing', async () => {
    renderApp('/users', makeContext(['customer.orders.view']))

    expect(await screen.findByRole('heading', { name: 'Accès refusé' })).toBeInTheDocument()
  })

  it('displays the current customer in the header', async () => {
    renderApp('/dashboard', makeContext([]))

    expect(await screen.findByTestId('current-customer')).toHaveTextContent('IKEA')
  })

  it('renders only the widgets the server composed', async () => {
    server.use(
      http.get(`${API}/dashboard`, () =>
        HttpResponse.json({
          data: {
            customer: { id: '01IKEA', name: 'IKEA' },
            period: null,
            widgets: [{ key: 'customer_orders_count', type: 'kpi', label: 'Commandes passées', size: 'small', position: 10, route: '/orders', periodAware: true, drilldown: null, data: { value: 7, unit: null } }],
          },
          meta: {},
        }),
      ),
    )

    renderApp('/dashboard', makeContext(ADMIN_PERMISSIONS))

    expect(await screen.findByText('Commandes passées')).toBeInTheDocument()
    expect(screen.getByText('7')).toBeInTheDocument()
  })
})
