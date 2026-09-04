import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { useUnplanFromTour } from '@/modules/planning/hooks/usePlanning'
import { useReleaseTour } from '@/modules/tours/hooks/useTours'
import type { Tour } from '@/modules/tours/types/tour'

/**
 * Conclure une composition sur carte : la confirmer, ou l'abandonner.
 *
 * **Ni l'une ni l'autre ne touche au statut.** Décision du 28 août 2026 :
 * confirmer ses modifications dans la carte ne veut pas dire confirmer la
 * tournée. Elle reste au brouillon, avec ce qu'on y a mis, et l'on décide plus
 * tard — depuis la fiche ou la colonne — de la confirmer pour de bon.
 *
 * C'est cette exigence qui a rendu nécessaire une réservation explicite : tant
 * que la fin de l'exclusivité coïncidait avec la sortie du brouillon, elle se
 * déduisait du statut. Elle se rend maintenant à part.
 */
export function useConcludePlan() {
  const { t } = useTranslation()
  const unplan = useUnplanFromTour()
  const release = useReleaseTour()

  return {
    isPending: unplan.isPending || release.isPending,

    /** La composition est bonne : on rend la tournée telle quelle. */
    confirm: (tour: Tour) =>
      release.mutate(tour.id, {
        onSuccess: () => toast.success(t('planning.planConfirmed')),
      }),

    /**
     * On abandonne : tout revient au pool, puis la tournée est rendue.
     *
     * Le retrait d'abord — une tournée rendue qui garde des commandes les
     * laisserait planifiées sans que personne ne s'en occupe.
     */
    abandon: (tour: Tour) => {
      const serviceIds = (tour.stops ?? []).flatMap((stop) => stop.orderServiceIds ?? [])

      const giveBack = () =>
        release.mutate(tour.id, {
          onSuccess: () => toast.success(t('planning.planAbandoned')),
        })

      if (serviceIds.length === 0) {
        giveBack()

        return
      }

      unplan.mutate({ tourId: tour.id, orderServiceIds: serviceIds }, { onSuccess: giveBack })
    },
  }
}
