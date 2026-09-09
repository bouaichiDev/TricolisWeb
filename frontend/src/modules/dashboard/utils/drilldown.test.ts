import { describe, expect, it } from 'vitest'

import { drilldownTo } from './drilldown'
import { OTHER_KEY } from '../components/charts/chartPalette'
import type { DashboardWidget, WidgetDrilldown } from '../types/dashboard'

/**
 * Ce qu'un clic sur une part emporte vers la liste.
 *
 * Le défaut corrigé ici tient en une phrase : la carte entière était un lien,
 * et viser la colonne du 3 septembre ouvrait les commandes **de tous les
 * temps**. Ces cas tiennent les quatre règles qui l'empêchent de revenir.
 */
function widget(drilldown: WidgetDrilldown | null, route: string | null = '/orders'): DashboardWidget {
  return {
    key: 'orders_per_day',
    type: 'columns',
    labelKey: 'dashboardWidgets.orders_per_day.label',
    periodLabelKey: null,
    size: 'large',
    position: 1,
    route,
    periodAware: true,
    drilldown,
    data: null,
  }
}

const DAYS: WidgetDrilldown = {
  seriesParam: 'status',
  fromParam: 'createdFrom',
  toParam: 'createdTo',
}

describe('forage depuis une part de graphe', () => {
  it('ouvre un jour par deux bornes identiques', () => {
    expect(drilldownTo(widget(DAYS), { day: '2026-09-03' })).toBe(
      '/orders?createdFrom=2026-09-03&createdTo=2026-09-03',
    )
  })

  it('combine la série et le jour quand les deux sont désignés', () => {
    expect(drilldownTo(widget(DAYS), { code: 'confirmed', day: '2026-09-03' })).toBe(
      '/orders?status=confirmed&createdFrom=2026-09-03&createdTo=2026-09-03',
    )
  })

  /**
   * Sans cette règle, une répartition lue sur un mois ouvrirait la liste
   * entière : plus de lignes que la part cliquée n'en compte, et rien pour le
   * dire.
   */
  it('reporte la période de l’écran quand aucun jour n’est visé', () => {
    expect(
      drilldownTo(widget(DAYS), {
        code: 'confirmed',
        period: { from: '2026-08-01', to: '2026-08-31' },
      }),
    ).toBe('/orders?status=confirmed&createdFrom=2026-08-01&createdTo=2026-08-31')
  })

  it('préfère le jour visé à la période, qui est plus large', () => {
    expect(
      drilldownTo(widget(DAYS), { day: '2026-08-12', period: { from: '2026-08-01', to: '2026-08-31' } }),
    ).toBe('/orders?createdFrom=2026-08-12&createdTo=2026-08-12')
  })

  /**
   * « Autres » recouvre plusieurs codes et n'en désigne aucun : l'envoyer comme
   * statut aurait rendu une liste vide sous une part qui ne l'est pas.
   */
  it('ne transmet pas la part « Autres »', () => {
    expect(drilldownTo(widget({ ...DAYS, fromParam: null, toParam: null }), { code: OTHER_KEY })).toBeNull()
  })

  /**
   * Le point de la correction : plutôt qu'un lien vers la liste entière, pas de
   * lien du tout. Une part qui mène ailleurs que là où elle promet vaut moins
   * qu'une part qui ne bouge pas.
   */
  it('ne rend aucun lien quand rien ne peut être transmis', () => {
    expect(drilldownTo(widget(null), { day: '2026-09-03' })).toBeNull()
    expect(drilldownTo(widget(DAYS, null), { day: '2026-09-03' })).toBeNull()
    expect(
      drilldownTo(widget({ seriesParam: 'status', fromParam: null, toParam: null }), {
        day: '2026-09-03',
      }),
    ).toBeNull()
  })
})
