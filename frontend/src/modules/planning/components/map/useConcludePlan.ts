import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { useUnplanFromTour } from '@/modules/planning/hooks/usePlanning'
import type { Tour } from '@/modules/tours/types/tour'
import { useChangeTourStatus } from '@/modules/tours/hooks/useTours'

/**
 * Conclure un plan commencé : le valider, ou l'annuler.
 *
 * Le §26 ne connaît que ces deux issues, et toutes deux font **sortir la
 * tournée du brouillon** — c'est là que l'exclusivité cesse d'elle-même. Une
 * troisième issue « je rends la main sans rien changer » n'aurait aucun effet
 * durable : la tournée resterait au brouillon, donc encore réservée, et les
 * boutons reviendraient à la réouverture. C'est exactement ce qui s'est produit
 * à la première version.
 *
 * **Annuler rend tout au pool avant d'annuler la tournée.** Sans cela, les
 * commandes resteraient prisonnières d'une tournée annulée : ni livrées, ni
 * replanifiables. Le geste est destructeur, et l'écran le fait confirmer.
 */
export function useConcludePlan() {
  const { t } = useTranslation()
  const unplan = useUnplanFromTour()
  const changeStatus = useChangeTourStatus()

  return {
    isPending: unplan.isPending || changeStatus.isPending,

    validate: (tour: Tour) => changeStatus.mutate({ id: tour.id, status: 'confirmed' }),

    cancel: (tour: Tour) => {
      const serviceIds = (tour.stops ?? []).flatMap((stop) => stop.orderServiceIds ?? [])

      const abandon = () =>
        changeStatus.mutate(
          { id: tour.id, status: 'cancelled' },
          { onSuccess: () => toast.success(t('planning.planCancelled')) },
        )

      if (serviceIds.length === 0) {
        abandon()

        return
      }

      // Le retrait d'abord : une tournee annulee qui garde ses commandes les
      // rend introuvables pour la planification suivante.
      unplan.mutate({ tourId: tour.id, orderServiceIds: serviceIds }, { onSuccess: abandon })
    },
  }
}
