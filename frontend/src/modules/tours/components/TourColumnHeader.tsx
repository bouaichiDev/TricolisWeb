import { Boxes, IdCard, Package, Route, Ruler, Truck, Users, Weight } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import type { Tour } from '../types/tour'

/**
 * Ce qu'une colonne dit d'une tournée avant qu'on l'ouvre.
 *
 * Un planificateur compare : celle-ci est pleine, celle-là part trop tard, cette
 * autre n'a pas de chauffeur. Ces trois réponses doivent tenir dans l'en-tête —
 * les chercher dans la fiche ferait perdre la comparaison.
 *
 * **Les libellés sont dans les infobulles, pas à l'écran.** Six intitulés en
 * toutes lettres prenaient plus de place que les six chiffres qu'ils nomment,
 * dans une colonne large de dix-huit rem. L'icône porte le sens, le `title` le
 * dit à qui hésite, et le chiffre reste lisible de loin.
 *
 * Une ressource non affectée s'écrit « à affecter » plutôt que de disparaître :
 * un blanc se lit comme un oubli d'affichage, pas comme un manque.
 */
export function TourColumnHeader({ tour }: { tour: Tour }) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-2">
      <dl className="flex flex-col gap-0.5 text-xs">
        <Row
          icon={IdCard}
          label={t('tours.fields.driver')}
          value={tour.driverName ?? t('tours.unassigned')}
          muted={tour.driverName === null || tour.driverName === undefined}
        />
        <Row
          icon={Truck}
          label={t('tours.fields.vehicle')}
          value={tour.vehicleRegistration ?? t('tours.unassigned')}
          muted={tour.vehicleRegistration === null || tour.vehicleRegistration === undefined}
        />
      </dl>

      <TourWindow from={tour.plannedStartAt} to={tour.plannedEndAt} />

      <dl className="grid grid-cols-3 gap-1 text-center">
        <Metric icon={Boxes} label={t('tours.fields.orders')} value={tour.orderCount ?? '—'} />
        <Metric icon={Users} label={t('tours.fields.customers')} value={tour.totalCustomers} />
        <Metric icon={Package} label={t('tours.fields.packages')} value={tour.totalPackages} />
        <Metric icon={Weight} label={t('tours.fields.weightShort')} value={`${tour.totalWeight}`} />
        <Metric icon={Ruler} label={t('tours.fields.volume')} value={`${tour.totalVolume}`} />
        {/* Le tiret dit « pas de valeur » ; l'infobulle dit pourquoi. Sans elle,
            un zéro et un calcul jamais fait se ressembleraient. */}
        <Metric
          icon={Route}
          label={
            tour.distanceMeters === 0
              ? `${t('tours.fields.distance')} — ${t('tours.notComputed')}`
              : t('tours.fields.distance')
          }
          value={tour.distanceMeters === 0 ? '—' : `${(tour.distanceMeters / 1000).toFixed(1)}`}
        />
      </dl>
    </div>
  )
}

/**
 * La fenêtre de la tournée : date au-dessus, heure en dessous, de part et
 * d'autre.
 *
 * « Du 27 août 2026 06:30 au 28 août 2026 00:30 » sur une ligne se lit comme
 * une phrase qu'il faut décortiquer. Deux colonnes se comparent d'un coup
 * d'œil : même jour ou non, et à quelle heure.
 */
function TourWindow({ from, to }: { from?: string | null; to?: string | null }) {
  const { t } = useTranslation()

  if (from === null || from === undefined) {
    return <p className="text-[11px] italic text-muted-foreground">{t('tours.noSchedule')}</p>
  }

  return (
    <div className="grid grid-cols-2 gap-2 rounded-md bg-muted/50 px-2 py-1.5 text-center">
      <Moment label={t('tours.from')} iso={from} />
      <Moment label={t('tours.to')} iso={to} />
    </div>
  )
}

function Moment({ label, iso }: { label: string; iso?: string | null }) {
  if (iso === null || iso === undefined) {
    return (
      <div>
        <p className="text-[10px] uppercase tracking-wide text-muted-foreground">{label}</p>
        <p className="text-xs text-muted-foreground">—</p>
      </div>
    )
  }

  const date = new Date(iso)
  const pad = (value: number) => String(value).padStart(2, '0')

  return (
    <div>
      <p className="text-[10px] uppercase tracking-wide text-muted-foreground">{label}</p>
      <p className="text-xs font-medium">
        {`${pad(date.getDate())}-${pad(date.getMonth() + 1)}-${date.getFullYear()}`}
      </p>
      <p className="text-sm font-semibold">{`${pad(date.getHours())}:${pad(date.getMinutes())}`}</p>
    </div>
  )
}

function Row({
  icon: Icon,
  label,
  value,
  muted,
}: {
  icon: typeof Truck
  label: string
  value: string
  muted: boolean
}) {
  return (
    <div className="flex items-center gap-1.5" title={label}>
      <Icon className="size-3.5 shrink-0 text-muted-foreground" aria-hidden />
      <dt className="sr-only">{label}</dt>
      <dd className={muted ? 'italic text-muted-foreground' : 'truncate font-medium'}>{value}</dd>
    </div>
  )
}

function Metric({
  icon: Icon,
  label,
  value,
}: {
  icon: typeof Package
  label: string
  value: string | number
}) {
  return (
    <div className="min-w-0 rounded-md border px-1 py-1" title={label}>
      <dt className="flex justify-center text-muted-foreground">
        <Icon className="size-3.5" aria-hidden />
        <span className="sr-only">{label}</span>
      </dt>
      <dd className="truncate text-base font-semibold leading-tight">{value}</dd>
    </div>
  )
}
