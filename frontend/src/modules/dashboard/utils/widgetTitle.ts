import type { TFunction } from 'i18next'

import type { DashboardPeriod, DashboardWidget } from '../types/dashboard'

/**
 * Le titre d'une carte, selon qu'une période est réglée ou non.
 *
 * Neuf cartes du catalogue nomment un **instant** — « Commandes du jour »,
 * « Tournées du jour », « Factures closes aujourd'hui ». Depuis qu'elles
 * suivent le filtre de dates, les laisser sous ce titre aurait produit le pire
 * des défauts : un chiffre **juste** sous un titre qui le contredit. Personne ne
 * vérifie un chiffre qui a l'air juste.
 *
 * La traduction de remplacement est **facultative**, et c'est ce qui rend la
 * règle tenable : les quarante autres cartes qui suivent la période — « par
 * statut », « par jour », « dernières commandes » — ne nomment aucun instant et
 * gardent leur titre sans qu'on ait à écrire quarante clés qui répéteraient la
 * première.
 */
export function widgetTitle(
  widget: DashboardWidget,
  period: DashboardPeriod | null,
  t: TFunction,
): string {
  const label = t(widget.labelKey)

  if (period === null || widget.periodLabelKey === null) {
    return label
  }

  return t(widget.periodLabelKey, { defaultValue: label })
}
