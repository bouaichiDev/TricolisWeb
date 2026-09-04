import { screen, within } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { DonutWidget } from './DonutWidget'
import { GaugeWidget } from './GaugeWidget'
import type { ChartSeries, DashboardWidget } from '../../types/dashboard'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

/**
 * Le camembert et la jauge, et ce qui les distingue de la barre.
 *
 * Ce ne sont pas trois habillages du même graphe : chacun répond à une question
 * que les deux autres posent mal. Ces tests tiennent ce qui le prouve — le
 * camembert nomme ses parts ailleurs que par la couleur, la jauge dit la part
 * **et** le tout, et aucun des deux n'invente une valeur quand il n'y a rien.
 */
function donut(series: ChartSeries[], labels: string | null = null): DashboardWidget {
  return {
    key: 'tours_by_status',
    type: 'donut',
    labelKey: 'dashboardWidgets.tours_by_status.label',
    size: 'medium',
    position: 1,
    route: null,
    data: { mode: 'share', source: null, labels, series },
  }
}

function gauge(value: number, total: number): DashboardWidget {
  return {
    key: 'planning_coverage_rate',
    type: 'gauge',
    labelKey: 'dashboardWidgets.planning_coverage_rate.label',
    size: 'small',
    position: 1,
    route: null,
    data: { value, total },
  }
}

function render(widget: DashboardWidget) {
  server.use(http.get(`${API}/statuses`, () => HttpResponse.json({ data: [], meta: {} })))

  return widget.type === 'gauge'
    ? renderWithProviders(<GaugeWidget widget={widget} />)
    : renderWithProviders(<DonutWidget widget={widget} />)
}

describe('camembert', () => {
  it('nomme chaque part dans sa légende, et donne sa valeur', async () => {
    render(donut([
      { code: 'draft', value: 4 },
      { code: 'completed', value: 22 },
    ]))

    const legend = await screen.findByRole('list')

    expect(within(legend).getByText('draft')).toBeInTheDocument()
    expect(within(legend).getByText('22')).toBeInTheDocument()
  })

  it('porte le total au centre de l’anneau', async () => {
    render(donut([
      { code: 'draft', value: 4 },
      { code: 'completed', value: 22 },
    ]))

    expect(await screen.findByText('26')).toBeInTheDocument()
  })

  /**
   * Ces codes viennent d'une énumération PHP, que le référentiel des statuts ne
   * connaît pas. Sans l'espace de traduction que le serveur désigne, la légende
   * afficherait `email` et `sms` en toutes lettres.
   */
  it('traduit les codes que le serveur rattache à un espace de traduction', async () => {
    render(donut([{ code: 'email', value: 184 }], 'communicationChannels'))

    expect(await screen.findByText('E-mail')).toBeInTheDocument()
  })

  it('le dit quand il n’y a rien à répartir', async () => {
    render(donut([]))

    expect(await screen.findByText(/Rien à afficher/)).toBeInTheDocument()
  })
})

describe('jauge', () => {
  /**
   * Le taux seul se retient mal : « 72 % » ne dit pas si l'on parle de neuf cas
   * sur douze ou de neuf cents sur mille deux cents.
   */
  it('affiche le taux et le compte, jamais l’un sans l’autre', async () => {
    render(gauge(34, 47))

    expect(await screen.findByText((content) => content.replace(/\s/gu, '') === '72%')).toBeInTheDocument()
    expect(screen.getByText(/34\s+sur\s+47/)).toBeInTheDocument()
  })

  /**
   * Zéro sur zéro n'est pas zéro pour cent : le premier dit qu'il n'y a rien à
   * mesurer, le second se lirait comme un échec.
   */
  it('ne rend pas 0 % quand il n’y a rien à mesurer', async () => {
    const { container } = render(gauge(0, 0))

    expect(await screen.findByText(/Rien à mesurer/)).toBeInTheDocument()
    expect(screen.queryByText(/0\s*%/)).not.toBeInTheDocument()

    // Et rien n'est tracé : un arc de longueur nulle au bout arrondi rendrait
    // un point, qu'on lirait comme une valeur minuscule.
    expect(container.querySelectorAll('circle[stroke-dasharray]')).toHaveLength(0)
  })

  it('ne dépasse jamais le tour complet', async () => {
    render(gauge(12, 10))

    expect(await screen.findByText((content) => content.replace(/\s/gu, '') === '100%')).toBeInTheDocument()
  })
})
