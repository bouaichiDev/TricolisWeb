import { CornerDownRight, Route, Sigma, Timer } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { formatDistance, formatTravelTime, type Cumulative } from '../utils/tourLegs'
import type { TourLeg } from '../types/tour'

interface TourBoardLegProps {
  leg: TourLeg
  /** Ce que le camion a déjà parcouru en arrivant ici, ce trajet compris. */
  cumulative: Cumulative
}

/**
 * Le trajet qui sépare deux arrêts, glissé entre leurs deux cartes.
 *
 * Sans lui, une colonne aligne des arrêts sans dire ce qui les sépare : deux
 * adresses de la même rue et deux villes distantes d'une heure s'y ressemblent.
 * Le temps de route est ce qui fait qu'un ordre tient ou non dans la journée.
 *
 * **Le cumul est là aussi.** Le temps d'un segment ne dit pas si le dernier
 * arrêt sera atteint avant la fermeture ; c'est le total depuis le départ qui
 * le dit, et le recalculer de tête à chaque arrêt est justement ce qu'on veut
 * éviter au planificateur.
 */
export function TourBoardLeg({ leg, cumulative }: TourBoardLegProps) {
  const { t } = useTranslation()

  // Un itinéraire calculé avant que la durée par segment ne soit conservée n'a
  // que sa distance. Le dire vaut mieux qu'afficher « 0 min », qui se lirait
  // comme deux arrêts à la même adresse.
  const hasTravelTime = leg.travelMinutes > 0
  const hasDistance = leg.distanceMeters > 0

  const worthCumulating =
    cumulative.minutes > leg.travelMinutes || cumulative.meters > leg.distanceMeters

  if (!hasTravelTime && !hasDistance) {
    return (
      <p className="flex items-center gap-1.5 pl-3 text-[11px] italic text-muted-foreground">
        <CornerDownRight className="size-3 shrink-0" aria-hidden />
        {t('tours.leg.notComputed')}
      </p>
    )
  }

  return (
    <div
      data-testid={`tour-leg-${leg.tourStopId ?? 'unknown'}`}
      className="flex items-center gap-2 rounded-md border border-dashed bg-muted/40 px-2 py-1"
    >
      <CornerDownRight className="size-3 shrink-0 text-muted-foreground" aria-hidden />

      <dl className="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px]">
        <Fact
          icon={Timer}
          label={t('tours.leg.travelTime')}
          value={hasTravelTime ? t('tours.leg.route', { value: formatTravelTime(leg.travelMinutes) }) : '—'}
          hint={hasTravelTime ? undefined : t('tours.leg.travelTimeMissing')}
          strong
        />

        {hasDistance ? (
          <Fact icon={Route} label={t('tours.leg.distance')} value={formatDistance(leg.distanceMeters)} />
        ) : null}

        {/* Sur le premier trajet, le cumul vaut le trajet lui-meme : l'ecrire
            une seconde fois n'apprend rien et brouille la bande. */}
        {worthCumulating ? (
          <Fact
            icon={Sigma}
            label={t('tours.leg.cumulative')}
            hint={t('tours.leg.cumulativeHint')}
            value={[
              cumulative.minutes > 0 ? formatTravelTime(cumulative.minutes) : null,
              cumulative.meters > 0 ? formatDistance(cumulative.meters) : null,
            ]
              .filter((part): part is string => part !== null)
              .join(' · ')}
          />
        ) : null}
      </dl>
    </div>
  )
}

/**
 * Une grandeur du trajet : l'icône porte le sens, le libellé reste lisible aux
 * lecteurs d'écran et en infobulle. Six intitulés en toutes lettres ne tiennent
 * pas dans la largeur d'une colonne.
 */
function Fact({
  icon: Icon,
  label,
  value,
  hint,
  strong,
}: {
  icon: typeof Timer
  label: string
  value: string
  hint?: string
  strong?: boolean
}) {
  return (
    <div className="flex items-center gap-1" title={hint === undefined ? label : `${label} — ${hint}`}>
      <Icon className="size-3 shrink-0 text-muted-foreground" aria-hidden />
      <dt className="sr-only">{label}</dt>
      <dd className={strong ? 'font-medium text-foreground' : 'text-muted-foreground'}>{value}</dd>
    </div>
  )
}
