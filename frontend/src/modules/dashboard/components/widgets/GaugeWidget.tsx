import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'

import { WidgetCard } from '../WidgetCard'
import type { DashboardWidget, GaugeData } from '../../types/dashboard'

const RADIUS = 42
const THICKNESS = 12
const CIRCUMFERENCE = 2 * Math.PI * RADIUS

/**
 * **Un** rapport, et son tout.
 *
 * Un camembert à deux secteurs répondrait à la même question en occupant deux
 * fois la place, et ferait passer le reste pour une catégorie alors qu'il n'est
 * qu'un reste — « ce qui n'est pas encore planifié » n'est pas une part au même
 * titre que « ce qui l'est ».
 *
 * Le taux **et** le compte sont affichés, jamais l'un sans l'autre. « 72 % » ne
 * dit pas si l'on parle de neuf cas sur douze ou de neuf cents sur mille deux
 * cents : la première mérite un haussement d'épaules, la seconde une réunion.
 *
 * **Une seule teinte**, celle de la première série. Pas de vert-orange-rouge :
 * ces taux n'ont pas de bon sens universel. Une part de stock réservée élevée
 * est excellente pour un commercial et inquiétante pour un logisticien, et
 * peindre l'un des deux en rouge trancherait à leur place.
 *
 * Un tout à zéro n'affiche pas 0 % — qui se lirait comme un échec — mais dit
 * qu'il n'y a rien à mesurer.
 */
export function GaugeWidget({ widget }: { widget: DashboardWidget }) {
  const { t, i18n } = useTranslation()
  const data = widget.data as GaugeData | null

  const value = data?.value ?? 0
  const total = data?.total ?? 0
  const ratio = total === 0 ? 0 : Math.min(value / total, 1)

  const percent = useMemo(
    () => new Intl.NumberFormat(i18n.language, { style: 'percent', maximumFractionDigits: 0 }),
    [i18n.language],
  )

  return (
    <WidgetCard title={t(widget.labelKey)} to={widget.route}>
      <div className="relative mx-auto aspect-square w-full max-w-[140px]">
        <svg viewBox="0 0 100 100" className="size-full -rotate-90" role="presentation">
          {/* La piste dit ce qui reste. Sans elle, un taux faible ressemblerait
              à un anneau presque absent plutôt qu'à un anneau presque vide. */}
          <circle
            cx="50"
            cy="50"
            r={RADIUS}
            fill="none"
            strokeWidth={THICKNESS}
            className="stroke-muted"
          />
          {/* Rien n'est dessiné à zéro. Un arc de longueur nulle avec un bout
              arrondi ne disparaît pas : il rend un point, et l'on croit lire
              une valeur minuscule là où il n'y en a aucune. */}
          {ratio > 0 ? (
            <circle
              cx="50"
              cy="50"
              r={RADIUS}
              fill="none"
              strokeWidth={THICKNESS}
              strokeLinecap="round"
              stroke="var(--chart-1)"
              strokeDasharray={`${ratio * CIRCUMFERENCE} ${CIRCUMFERENCE}`}
            />
          ) : null}
        </svg>

        <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
          {total === 0 ? (
            <span className="px-2 text-center text-xs text-muted-foreground">
              {t('dashboard.gaugeEmpty')}
            </span>
          ) : (
            <>
              <span className="text-2xl font-semibold">{percent.format(ratio)}</span>
              <span className="text-xs text-muted-foreground">
                {value} {t('dashboard.gaugeOutOf', { total })}
              </span>
            </>
          )}
        </div>
      </div>
    </WidgetCard>
  )
}
