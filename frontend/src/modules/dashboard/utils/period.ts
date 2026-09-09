import type { DashboardPeriod } from '../types/dashboard'

/**
 * Les quatre fenêtres qu'on demande vraiment, et la saisie libre pour le reste.
 *
 * Quatre raccourcis plutôt qu'un calendrier seul : « aujourd'hui » et « les
 * trente derniers jours » sont l'essentiel des demandes, et les composer à la
 * main coûtait deux sélections de date à chaque consultation. Les deux champs
 * restent là pour la question précise — un mois clos, une semaine d'inventaire.
 */
export type PeriodPreset = 'today' | 'last7' | 'last30' | 'thisMonth'

export const PERIOD_PRESETS: PeriodPreset[] = ['today', 'last7', 'last30', 'thisMonth']

/**
 * Le jour, tel que le serveur l'attend — `Y-m-d`, en heure **locale**.
 *
 * `toISOString()` aurait été plus court et faux d'un jour : il convertit en UTC,
 * et un utilisateur à l'est de Greenwich qui demande « aujourd'hui » avant deux
 * heures du matin recevrait la veille. Le tableau de bord se lit dans le fuseau
 * de celui qui le regarde, pas dans celui du méridien.
 */
export function toIsoDay(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${date.getFullYear()}-${month}-${day}`
}

function shifted(days: number): Date {
  const date = new Date()
  date.setDate(date.getDate() + days)

  return date
}

/**
 * La période d'un raccourci, bornes comprises.
 *
 * « Sept derniers jours » commence donc à `aujourd'hui - 6` : partir de
 * `aujourd'hui - 7` en compterait huit, et le graphe montrerait une colonne de
 * plus que le libellé n'en annonce.
 */
export function presetPeriod(preset: PeriodPreset): DashboardPeriod {
  const today = toIsoDay(new Date())

  switch (preset) {
    case 'today':
      return { from: today, to: today }
    case 'last7':
      return { from: toIsoDay(shifted(-6)), to: today }
    case 'last30':
      return { from: toIsoDay(shifted(-29)), to: today }
    case 'thisMonth': {
      const now = new Date()

      return { from: toIsoDay(new Date(now.getFullYear(), now.getMonth(), 1)), to: today }
    }
  }
}

export function isPreset(period: DashboardPeriod | null, preset: PeriodPreset): boolean {
  if (period === null) return false

  const candidate = presetPeriod(preset)

  return candidate.from === period.from && candidate.to === period.to
}

/**
 * Une période complète, ou rien.
 *
 * Une seule borne n'est pas une période : le serveur la refuse en 422, et
 * compléter la borne manquante ici aurait filtré sur un intervalle que personne
 * n'a choisi. Les bornes à l'envers sont écartées de la même façon — c'est une
 * saisie en cours, pas une demande.
 */
export function periodFrom(from: string | null, to: string | null): DashboardPeriod | null {
  if (from === null || to === null || from === '' || to === '') return null

  return from > to ? null : { from, to }
}
