import { AlertTriangle, Check } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { WidgetCard } from '../WidgetCard'
import type { AlertData, DashboardWidget } from '../../types/dashboard'

/**
 * Un compte qui appelle une action.
 *
 * La seule différence avec un compteur est ce que zéro veut dire. Ici, zéro est
 * une **bonne nouvelle** : rien à reprendre, rien à corriger. La carte le dit
 * sobrement, sans teinte ni alarme — colorer un tableau de bord entier en
 * ambre parce que quatre alertes y figurent à zéro aurait appris à ne plus les
 * regarder.
 *
 * Ce ne sont pas des enregistrements : aucune table `alerts` n'est née pour
 * cela. Ce sont des projections — des services en échec, des envois ratés, des
 * réclamations ouvertes — que le tableau de bord compte au moment où on le
 * regarde.
 */
export function AlertWidget({ widget }: { widget: DashboardWidget }) {
  const { t } = useTranslation()
  const count = (widget.data as AlertData | null)?.value ?? 0
  const raised = count > 0

  return (
    <WidgetCard title={t(widget.labelKey)} to={widget.route} tone={raised ? 'attention' : 'default'}>
      <span className="flex items-center gap-2">
        {raised ? (
          <AlertTriangle className="size-5 text-warning" aria-hidden />
        ) : (
          <Check className="size-5 text-success" aria-hidden />
        )}
        <span className="text-3xl font-semibold tabular-nums">{count}</span>
      </span>
    </WidgetCard>
  )
}
