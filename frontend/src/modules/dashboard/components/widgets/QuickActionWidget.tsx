import { Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { WidgetCard } from '../WidgetCard'
import type { DashboardWidget } from '../../types/dashboard'

/**
 * Un raccourci vers ce qu'un métier fait dix fois par jour.
 *
 * Aucune donnée : la carte n'affiche qu'un libellé et une destination, tous
 * deux connus du catalogue. C'est le seul type dont `data` vaut `null`, et le
 * seul pour lequel aucune requête n'est jouée.
 *
 * La permission qu'elle exige est celle de **l'action**, pas de la lecture :
 * `orders.create`, et non `orders.view`. Un rôle qui ne peut que consulter les
 * commandes ne se voit donc pas proposer d'en créer une — le filtre a eu lieu
 * côté serveur, et cette carte n'existe déjà plus quand il manque le droit.
 */
export function QuickActionWidget({ widget }: { widget: DashboardWidget }) {
  const { t } = useTranslation()

  return (
    <WidgetCard title={t('dashboard.quickAction')} to={widget.route}>
      <span className="flex items-center gap-2 text-base font-medium">
        <Plus className="size-4 shrink-0 text-primary" aria-hidden />
        {t(widget.labelKey)}
      </span>
    </WidgetCard>
  )
}
