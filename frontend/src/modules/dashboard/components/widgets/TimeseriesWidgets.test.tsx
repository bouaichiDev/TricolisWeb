import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { ColumnsWidget } from './ColumnsWidget'
import { LinesWidget } from './LinesWidget'
import { niceScale } from '../charts/timeScale'
import type { DashboardWidget, TimeseriesSerie } from '../../types/dashboard'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

/**
 * Les deux graphes qui portent le temps.
 *
 * Ce qu'ils doivent tenir tient en trois points, et aucun ne se voit tant qu'il
 * tient : les graduations sont des nombres qu'on lit, chaque série est nommée
 * ailleurs que par sa couleur, et le survol rend les valeurs du jour visé sans
 * qu'on ait à deviner lequel.
 */
/** Une série de dix valeurs, dont on fixe seulement les premières. */
function values(head: number[]): number[] {
  return [...head, ...Array.from({ length: 10 - head.length }, () => 0)]
}

function timeseries(type: 'columns' | 'lines', series: TimeseriesSerie[]): DashboardWidget {
  return {
    key: type === 'columns' ? 'orders_per_day' : 'orders_trend',
    type,
    labelKey: `dashboardWidgets.${type === 'columns' ? 'orders_per_day' : 'orders_trend'}.label`,
    periodLabelKey: null,
    size: 'large',
    position: 1,
    route: null,
    periodAware: true,
    drilldown: null,
    data: {
      // Dix jours, et non trois : l'axe n'écrit au plus que cinq dates, et
      // avec trois buckets il les écrirait toutes — le jour survolé, affiché
      // en tête, deviendrait alors indiscernable de son étiquette d'axe.
      buckets: Array.from({ length: 10 }, (_, index) => `2026-09-0${index + 1}`.slice(0, 10)),
      series,
      source: null,
      labels: 'orderTrend',
    },
  }
}

/**
 * Le même graphe, mais **cliquable** — comme le catalogue le déclare.
 *
 * C'est le défaut que ces cartes avaient : la carte entière était un lien, et
 * viser la colonne du 3 septembre ouvrait les commandes de tous les temps.
 */
function drilling(widget: DashboardWidget): DashboardWidget {
  return {
    ...widget,
    route: '/orders',
    drilldown: { seriesParam: 'status', fromParam: 'createdFrom', toParam: 'createdTo' },
  }
}

function render(widget: DashboardWidget) {
  server.use(http.get(`${API}/statuses`, () => HttpResponse.json({ data: [], meta: {} })))

  return widget.type === 'columns'
    ? renderWithProviders(<ColumnsWidget widget={widget} period={null} />)
    : renderWithProviders(<LinesWidget widget={widget} period={null} />)
}

describe('échelle verticale', () => {
  /**
   * Diviser le plafond en trois donnait « 100 / 67 / 33 » — des nombres exacts
   * que personne ne lit. On choisit le pas d'abord, le plafond ensuite.
   */
  it('choisit des graduations rondes plutôt qu’un nombre de lignes fixe', () => {
    expect(niceScale(37).ticks).toEqual([0, 10, 20, 30, 40])
    expect(niceScale(90).ticks).toEqual([0, 50, 100])
    expect(niceScale(6).ticks).toEqual([0, 2, 4, 6])
  })

  /** Une graduation à 0,5 promettrait des demi-commandes. */
  it('ne descend jamais sous le pas de un', () => {
    expect(niceScale(1).ticks).toEqual([0, 1])
    expect(niceScale(3).ticks).toEqual([0, 1, 2, 3])
  })

  it('rend une échelle utilisable quand tout vaut zéro', () => {
    expect(niceScale(0)).toEqual({ ceiling: 1, ticks: [0, 1] })
  })
})

describe('colonnes empilées', () => {
  it('nomme chaque série dans la légende, jamais par la seule couleur', async () => {
    render(timeseries('columns', [
      { code: 'created', values: values([3, 5, 2]) },
      { code: 'completed', values: values([1, 4, 6]) },
    ]))

    const legend = await screen.findByRole('list')

    expect(within(legend).getByText('Créées')).toBeInTheDocument()
    expect(within(legend).getByText('Achevées')).toBeInTheDocument()
  })

  /**
   * Le survol est capté par une bande large de tout l'intervalle : viser une
   * colonne de six pixels demanderait une précision que personne n'a.
   */
  it('rend les valeurs du jour survolé, et le nomme', async () => {
    const user = userEvent.setup()

    render(timeseries('columns', [
      { code: 'created', values: values([3, 5, 2]) },
      { code: 'completed', values: values([1, 4, 6]) },
    ]))

    await user.hover((await screen.findAllByRole('button'))[1])

    // Le libellé du jour passe par `Intl`, dont la ponctuation dépend de la
    // version d'ICU : on vérifie le jour et le mois, pas le point abréviatif.
    expect(await screen.findByText(/2\s*sept/)).toBeInTheDocument()

    const legend = screen.getByRole('list')
    expect(within(legend).getByText('5')).toBeInTheDocument()
    expect(within(legend).getByText('4')).toBeInTheDocument()
  })

  it('ne dessine rien pour un jour vide', async () => {
    const { container } = render(timeseries('columns', [{ code: 'created', values: values([0, 5]) }]))

    await screen.findByRole('list')

    // Une seule pile a une hauteur : les deux jours à zéro n'ont pas de segment,
    // et une colonne d'un pixel se lirait comme une valeur minuscule.
    const filled = [...container.querySelectorAll('[style*="flex-grow"]')]
    expect(filled).toHaveLength(1)
  })
})

describe('courbes', () => {
  it('trace une polyligne par série', async () => {
    const { container } = render(timeseries('lines', [
      { code: 'created', values: values([3, 5, 2]) },
      { code: 'completed', values: values([1, 4, 6]) },
    ]))

    await screen.findByRole('list')

    expect(container.querySelectorAll('polyline')).toHaveLength(2)
  })

  /**
   * Une interpolation douce inventerait des valeurs entre deux jours — un creux
   * qui n'a pas eu lieu, un pic qui dépasse le maximum réel.
   */
  it('relie les jours par des segments droits, sans lissage', async () => {
    const { container } = render(timeseries('lines', [{ code: 'created', values: values([0, 10, 5]) }]))

    await screen.findByRole('list')

    const points = container.querySelector('polyline')?.getAttribute('points')

    // Un point par jour, le premier au bord gauche, le dernier au bord droit :
    // une courbe relie des instants, elle n'occupe pas des intervalles. Le
    // dernier jour vaut zéro, donc y = 100 — le bas du cadre.
    expect(points?.split(' ')).toHaveLength(10)
    expect(points?.startsWith('0,')).toBe(true)
    expect(points?.endsWith('100,100')).toBe(true)
  })

  it('le dit quand il n’y a rien à tracer', async () => {
    render(timeseries('lines', []))

    expect(await screen.findByText(/Rien à afficher/)).toBeInTheDocument()
  })
})

describe('forage vers la liste', () => {
  it('ouvre le jour visé, et non la liste entière', async () => {
    render(drilling(timeseries('columns', [{ code: 'confirmed', values: values([5, 3]) }])))

    // Une bande par jour, nommée par sa date : c'est la cible du survol, et
    // donc celle du clic — on ouvre ce qu'on vient de lire.
    expect(await screen.findByRole('link', { name: '2026-09-03' })).toHaveAttribute(
      'href',
      '/orders?createdFrom=2026-09-03&createdTo=2026-09-03',
    )
  })

  /**
   * La légende est le chemin du clavier : un arc ou une bande se visent à la
   * souris et nulle part ailleurs.
   */
  it('donne à chaque série un lien vers sa liste', async () => {
    render(drilling(timeseries('columns', [{ code: 'confirmed', values: values([5]) }])))

    expect(await screen.findByRole('link', { name: /confirmed/ })).toHaveAttribute(
      'href',
      '/orders?status=confirmed',
    )
  })

  /**
   * Sans descripteur, aucune part n'est un lien : mieux vaut une colonne qui ne
   * bouge pas qu'une colonne qui mène ailleurs que là où elle promet.
   */
  it('ne rend rien cliquable quand le catalogue ne déclare pas de forage', async () => {
    render(timeseries('columns', [{ code: 'confirmed', values: values([5]) }]))

    expect(await screen.findByText('confirmed')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: '2026-09-03' })).not.toBeInTheDocument()
  })
})
