import { screen } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { DashboardPage } from './DashboardPage'
import type { DashboardWidget } from '../types/dashboard'
import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

/**
 * Le tableau de bord rend ce qu'on lui donne, et rien d'autre.
 *
 * Ces tests ne vérifient donc **pas** qu'un widget interdit est masqué : il
 * n'arrive jamais jusqu'ici. Cette garantie-là est tenue par le backend, et
 * `tests/Feature/Api/V1/Dashboard/DashboardTest.php` la vérifie — y compris
 * l'absence de sa valeur dans le JSON. Ce qui se joue ici est le rendu : cinq
 * types, un squelette, une erreur, un écran vide.
 */
function dashboardHandler(widgets: DashboardWidget[]) {
  return http.get(`${API}/dashboard`, () =>
    HttpResponse.json({
      data: { organization: { id: 'ORG-1', name: 'Atlas Transport' }, widgets },
      meta: [],
    }),
  )
}

const kpi: DashboardWidget = {
  key: 'orders_today',
  type: 'kpi',
  labelKey: 'dashboardWidgets.orders_today.label',
  size: 'small',
  position: 1,
  route: '/orders',
  data: { value: 12, unit: null },
}

const viewer = withPermissions(['dashboard.view'])

function renderDashboard(membership = viewer) {
  renderWithProviders(<DashboardPage />, { route: '/dashboard', membership })
}

describe('tableau de bord', () => {
  it('rend un compteur avec sa valeur et son lien', async () => {
    server.use(dashboardHandler([kpi]))
    renderDashboard()

    expect(await screen.findByText('12')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Commandes du jour/ })).toHaveAttribute(
      'href',
      '/orders',
    )
  })

  it('rend les cinq types de widget', async () => {
    server.use(
      dashboardHandler([
        kpi,
        {
          ...kpi,
          key: 'services_failed',
          type: 'alert',
          labelKey: 'dashboardWidgets.services_failed.label',
          route: null,
          data: { value: 3 },
        },
        {
          ...kpi,
          key: 'orders_by_status',
          type: 'chart',
          labelKey: 'dashboardWidgets.orders_by_status.label',
          data: { mode: 'share' as const, source: null, series: [{ code: 'draft', value: 4 }] },
        },
        {
          ...kpi,
          key: 'recent_orders',
          type: 'list',
          labelKey: 'dashboardWidgets.recent_orders.label',
          data: {
            items: [
              {
                id: 'o1',
                title: 'CMD-001',
                subtitle: 'Atlas',
                status: null,
                statusSource: null,
                date: null,
                route: '/orders/o1',
              },
            ],
          },
        },
        {
          ...kpi,
          key: 'new_order',
          type: 'quick_action',
          labelKey: 'dashboardWidgets.new_order.label',
          route: '/orders/create',
          data: null,
        },
      ]),
    )
    renderDashboard()

    expect(await screen.findByText('12')).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
    expect(screen.getByText('draft')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /CMD-001/ })).toHaveAttribute('href', '/orders/o1')
    expect(screen.getByText('Nouvelle commande')).toBeInTheDocument()
  })

  /**
   * Le §46 : pas de repli sur les quatre anciennes cartes le temps du
   * chargement. Les afficher une seconde montrait à chacun un tableau de bord
   * qui n'était pas le sien, puis le remplaçait — ce qui ressemble à une panne.
   */
  it('ne montre aucune carte avant la réponse', () => {
    server.use(dashboardHandler([kpi]))
    renderDashboard()

    expect(screen.queryByText('12')).not.toBeInTheDocument()
    expect(screen.queryByText('Clients')).not.toBeInTheDocument()
  })

  it('propose de réessayer en cas d’erreur, sans rien afficher d’autre', async () => {
    server.use(http.get(`${API}/dashboard`, () => HttpResponse.json({ message: 'Boom' }, { status: 500 })))
    renderDashboard()

    expect(await screen.findByRole('button', { name: 'Réessayer' })).toBeInTheDocument()
    expect(screen.queryByText('Clients')).not.toBeInTheDocument()
  })

  it('explique un tableau de bord vide', async () => {
    server.use(dashboardHandler([]))
    renderDashboard()

    expect(
      await screen.findByText('Votre tableau de bord ne contient actuellement aucun widget.'),
    ).toBeInTheDocument()
  })

  /**
   * Le raccourci vers le réglage n'est proposé qu'à qui peut s'en servir :
   * sans la permission, il aurait mené à un écran qui refuse.
   */
  it('ne propose le réglage qu’avec dashboard.configure', async () => {
    server.use(dashboardHandler([]))
    renderDashboard()

    expect(await screen.findByText(/aucun widget/)).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Configurer les rôles' })).not.toBeInTheDocument()
  })

  it('propose le réglage à qui détient la permission', async () => {
    server.use(dashboardHandler([]))
    renderDashboard(withPermissions(['dashboard.view', 'dashboard.configure']))

    expect(await screen.findByRole('link', { name: 'Configurer les rôles' })).toHaveAttribute(
      'href',
      '/roles',
    )
  })
})
