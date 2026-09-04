import { screen, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'

import { ChartWidget } from './ChartWidget'
import type { ChartSeries, DashboardWidget } from '../../types/dashboard'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'
import { HttpResponse, http } from 'msw'

/**
 * Ce que le graphe doit tenir, et qu'on ne voit pas en le regardant.
 *
 * Trois garanties, chacune invisible tant qu'elle tient : chaque série est
 * **nommée** quelque part — la couleur ne porte jamais seule l'identité — ; la
 * neuvième série ne reçoit pas une neuvième teinte inventée ; et des montants en
 * devises différentes ne sont jamais dessinés sur une échelle commune.
 */
function chartWidget(series: ChartSeries[], mode: 'share' | 'amounts' = 'share'): DashboardWidget {
  return {
    key: 'orders_by_status',
    type: 'chart',
    labelKey: 'dashboardWidgets.orders_by_status.label',
    size: 'medium',
    position: 1,
    route: null,
    data: { mode, source: null, series },
  }
}

function renderChart(widget: DashboardWidget, referential: Array<{ code: string; label: string }> = []) {
  // Le référentiel des statuts est interrogé pour nommer les codes ; sans
  // gestionnaire, `onUnhandledRequest: 'error'` ferait échouer le test.
  server.use(
    http.get(`${API}/statuses`, () =>
      HttpResponse.json({
        data: referential.map((status, index) => ({
          id: `st-${index}`,
          source: 'order',
          status: index,
          code: status.code,
          label: status.label,
          icon: null,
          active: true,
          position: index,
        })),
        meta: {},
      }),
    ),
  )

  return renderWithProviders(<ChartWidget widget={widget} />)
}

describe('graphe de répartition', () => {
  it('nomme chaque série et donne sa valeur, sans dépendre de la couleur', async () => {
    renderChart(chartWidget([
      { code: 'draft', value: 2 },
      { code: 'confirmed', value: 6 },
    ]))

    const legend = await screen.findByRole('list')

    expect(within(legend).getByText('draft')).toBeInTheDocument()
    expect(within(legend).getByText('6')).toBeInTheDocument()
  })

  it('annonce le total, que la barre se contente de répartir', async () => {
    renderChart(chartWidget([
      { code: 'draft', value: 2 },
      { code: 'confirmed', value: 6 },
    ]))

    expect(await screen.findByText('8')).toBeInTheDocument()
  })

  it('donne la part de chaque série', async () => {
    renderChart(chartWidget([
      { code: 'draft', value: 1 },
      { code: 'confirmed', value: 3 },
    ]))

    // L'espace avant le signe est une espace fine insécable en français : la
    // comparer littéralement rendrait le test dépendant de la locale du poste.
    const percent = (text: string) => (content: string) => content.replace(/\s/gu, '') === text

    expect(await screen.findByText(percent('25%'))).toBeInTheDocument()
    expect(screen.getByText(percent('75%'))).toBeInTheDocument()
  })

  /**
   * Au-delà de huit, la queue se replie plutôt que de recevoir une teinte de
   * plus : une neuvième couleur serait indistinguable d'une autre sous vision
   * altérée, et la palette cesserait de garantir ce qu'elle garantit.
   */
  it('replie la neuvième série sur « Autres » plutôt que d’inventer une teinte', async () => {
    const series = Array.from({ length: 11 }, (_, index) => ({
      code: `statut-${index}`,
      value: index + 1,
    }))

    renderChart(chartWidget(series))

    expect(await screen.findByText('Autres')).toBeInTheDocument()
    expect(screen.queryByText('statut-8')).not.toBeInTheDocument()

    // 9 + 10 + 11, les trois séries repliées.
    expect(screen.getByText('30')).toBeInTheDocument()
  })

  /**
   * Le seul graphe sans barre, et c'est le propos : deux montants libellés dans
   * deux monnaies ne se rangent pas sur la même règle.
   */
  it('rend des montants en devises sans barre proportionnelle', async () => {
    const { container } = renderChart(
      chartWidget([
        { code: 'EUR', value: 1200 },
        { code: 'MAD', value: 8000 },
      ], 'amounts'),
    )

    expect(await screen.findByText('EUR')).toBeInTheDocument()
    expect(screen.getByText('MAD')).toBeInTheDocument()
    expect(container.querySelectorAll('[style*="flex-grow"]')).toHaveLength(0)
  })

  /**
   * Le serveur trie par code — `completed, confirmed, draft` — ce qui prend le
   * pipeline à l'envers. Le référentiel porte le rang que l'administrateur a
   * réglé ; c'est lui qui fait foi.
   */
  it('remet les séries dans l’ordre du cycle de vie, pas dans celui du code', async () => {
    const widget = chartWidget([
      { code: 'completed', value: 60 },
      { code: 'confirmed', value: 901 },
      { code: 'draft', value: 2 },
    ])
    widget.data = { mode: 'share', source: 'order', series: (widget.data as never as { series: ChartSeries[] }).series }

    renderChart(widget, [
      { code: 'draft', label: 'Brouillon' },
      { code: 'confirmed', label: 'Confirmée' },
      { code: 'completed', label: 'Terminée' },
    ])

    // On attend le libellé, pas la liste : celle-ci est rendue tout de suite
    // avec les codes bruts, et l'ordre ne change qu'une fois le référentiel
    // arrivé.
    await screen.findByText('Brouillon')

    const rows = within(screen.getByRole('list')).getAllByRole('listitem')

    expect(rows.map((row) => row.textContent?.slice(0, 4))).toEqual(['Brou', 'Conf', 'Term'])
  })

  it('le dit quand il n’y a rien à répartir', async () => {
    renderChart(chartWidget([]))

    expect(await screen.findByText(/Rien à afficher/)).toBeInTheDocument()
  })
})
