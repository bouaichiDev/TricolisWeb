import { useTranslation } from 'react-i18next'

import { DashboardGrid } from './DashboardGrid'
import type { DashboardPeriod, DashboardWidget } from '../types/dashboard'

interface DashboardBoardProps {
  widgets: DashboardWidget[]
  period: DashboardPeriod | null
}

/**
 * Les cartes affichées, dans l'ordre que le rôle a configuré.
 *
 * **Sans période, tout s'affiche** — c'est le tableau de bord d'origine. Dès
 * qu'une période est réglée, les cartes qui ne savent pas la suivre sont
 * **retirées**, pas déplacées en bas d'écran : une carte qui reste sous un
 * filtre qu'elle ignore affiche un chiffre juste sous un contexte qui dit autre
 * chose, et rien ne le contredit. La reléguer plus bas ne réglait rien — elle
 * était toujours là, et l'ordre configuré s'en trouvait défait.
 *
 * Ce qui disparaît n'a pas de date à filtrer, et n'en aura pas : les soldes de
 * stock portent l'état courant — il n'existe pas de solde « au 12 août » — et
 * les dénombrements comptent ce qui existe, pas ce qui s'est passé.
 *
 * **Les actions rapides restent.** Elles ne portent aucun chiffre : ce sont des
 * boutons, et un bouton « Nouvelle commande » ne peut pas mentir sur une
 * période. Les retirer aurait fait disparaître une fonction pour rien.
 *
 * Le compte des cartes retirées est écrit sous la grille. Sans lui, un tableau
 * de bord qui maigrit d'un tiers au premier filtre ressemble à une panne.
 */
export function DashboardBoard({ widgets, period }: DashboardBoardProps) {
  const { t } = useTranslation()

  if (period === null) {
    return <DashboardGrid widgets={widgets} period={null} />
  }

  const shown = widgets.filter((widget) => widget.periodAware || widget.type === 'quick_action')
  const hidden = widgets.length - shown.length

  return (
    <div className="flex flex-col gap-3">
      {shown.length === 0 ? (
        <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
          {t('dashboard.period.noneApply')}
        </p>
      ) : (
        <DashboardGrid widgets={shown} period={period} />
      )}

      {hidden > 0 ? (
        <p className="text-xs text-muted-foreground">
          {t('dashboard.period.hiddenCount', { count: hidden })}
        </p>
      ) : null}
    </div>
  )
}
