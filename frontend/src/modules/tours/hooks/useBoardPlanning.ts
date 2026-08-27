import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { planPayloadOf, type PlanningDragPayload } from '@/modules/planning/dnd'
import { usePlanIntoTour, useUnplanFromTour } from '@/modules/planning/hooks/usePlanning'
import type { PlanningRejection } from '@/modules/planning/types/pool'

/**
 * Verser dans une tournée, et en retirer, depuis la vue en colonnes.
 *
 * Les deux gestes partagent la même façon de rendre compte : le serveur décide,
 * l'écran rapporte — ce qui est passé, et chaque refus avec son motif. Les
 * réunir ici évite d'écrire ce compte rendu deux fois.
 */
export function useBoardPlanning() {
  const { t } = useTranslation()
  const plan = usePlanIntoTour()
  const unplan = useUnplanFromTour()

  /** Traduit les refus rendus par le serveur ; il ne les invente pas. */
  const warn = (rejected: PlanningRejection[]) => {
    for (const refusal of rejected) {
      toast.warning(t(`planning.rejected.${refusal.reason}`, { defaultValue: refusal.reason }))
    }
  }

  const send = (tourId: string, payload: { orderIds?: string[]; orderServiceIds?: string[] }) =>
    plan.mutate(
      { tourId, ...payload },
      {
        onSuccess: (result) => {
          if (result.planned.length > 0) {
            toast.success(t('planning.planned', { count: result.planned.length }))
          }

          warn(result.rejected)
        },
      },
    )

  return {
    isPending: plan.isPending,
    send,
    drop: (tourId: string, drag: PlanningDragPayload) => send(tourId, planPayloadOf(drag)),
    release: (tourId: string, orderServiceIds: string[]) =>
      unplan.mutate(
        { tourId, orderServiceIds },
        {
          onSuccess: (result) => {
            if (result.unplanned.length > 0) {
              toast.success(t('planning.unplanned', { count: result.unplanned.length }))
            }

            warn(result.rejected)
          },
        },
      ),
  }
}
