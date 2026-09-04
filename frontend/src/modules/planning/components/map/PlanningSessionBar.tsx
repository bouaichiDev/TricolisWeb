import { Check, Lock, UserRound, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import type { Tour } from '@/modules/tours/types/tour'
import { Button } from '@/shared/components/ui/button'

interface PlanningSessionBarProps {
  userName: string
  selected: Tour | null
  /** La tournée ouverte attend-elle d'être conclue ? Déduit de son contenu. */
  awaitsConclusion: boolean
  isPending: boolean
  onValidate: () => void
  onCancel: () => void
}

/**
 * Où en est la session de planification, et comment en sortir.
 *
 * Une tournée réservée ferme les autres tant qu'on ne l'a pas rendue. La barre
 * le dit et offre les deux sorties : confirmer ce qu'on a composé, ou
 * l'abandonner.
 *
 * **Aucune des deux ne change le statut.** Confirmer sur la carte ne confirme
 * pas la tournée : elle reste au brouillon, et c'est depuis sa colonne ou sa
 * fiche qu'on décide de la faire changer d'état.
 *
 * **L'état vient des données, pas d'une variable.** Une réservation qui
 * disparaîtrait en fermant la fenêtre n'en serait pas une.
 *
 * Quand la tournée regardée appartient à quelqu'un d'autre, c'est son nom qui
 * s'affiche : le §25 veut « Planification en cours par Sara Amrani / Mode
 * lecture seule », de quoi savoir à qui demander de libérer.
 */
export function PlanningSessionBar({
  userName,
  selected,
  awaitsConclusion,
  isPending,
  onValidate,
  onCancel,
}: PlanningSessionBarProps) {
  const { t } = useTranslation()

  const holder = selected?.status === 'draft' ? (selected.plannedBy ?? null) : null

  return (
    <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-card px-3 py-2">
      <p className="flex items-center gap-2 text-sm">
        <UserRound className="size-4 text-muted-foreground" aria-hidden />
        <span className="text-muted-foreground">{t('planning.currentUser')}</span>
        <span className="font-medium">{userName}</span>
      </p>

      <div className="flex flex-wrap items-center gap-3">
        {holder === null ? null : (
          <p className="flex items-center gap-1.5 text-sm">
            <Lock className="size-4 text-muted-foreground" aria-hidden />
            <span className="text-muted-foreground">{t('planning.heldBy')}</span>
            <span className="font-medium">{holder.name}</span>
          </p>
        )}

        {awaitsConclusion ? (
          <span className="flex items-center gap-2">
            {/* Les deux seules sorties : le §26 interdit de laisser une tournée
                réservée indéfiniment. */}
            <span className="text-sm text-muted-foreground">{t('planning.mustConclude')}</span>

            <Button type="button" size="sm" disabled={isPending} onClick={onValidate}>
              <Check className="size-4" aria-hidden />
              {t('planning.confirmPlan')}
            </Button>

            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={isPending}
              onClick={onCancel}
            >
              <X className="size-4" aria-hidden />
              {t('planning.abandonPlan')}
            </Button>
          </span>
        ) : (
          <p className="text-sm text-muted-foreground">
            {selected === null
              ? t('planning.pickTour')
              : t('planning.receiving', { number: selected.tourNumber })}
          </p>
        )}
      </div>
    </div>
  )
}
