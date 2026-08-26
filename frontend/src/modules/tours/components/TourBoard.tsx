import { Clock, MapPin, Package, Route, Users, Weight } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { formatDate } from '@/shared/utils/format'

import type { Tour } from '../types/tour'

interface TourBoardProps {
  tours: Tour[]
  emptyMessage: string
}

/**
 * Les tournées côte à côte, une colonne chacune.
 *
 * C'est la lecture d'un planificateur : il compare des tournées entre elles —
 * celle-ci est pleine, celle-là part trop tard — ce qu'un tableau ligne à ligne
 * ne montre pas. Chaque colonne porte son en-tête et ses arrêts dans l'ordre.
 *
 * Le défilement est **horizontal et propre à ce panneau** : la page ne doit pas
 * se décaler entière parce qu'on regarde la sixième tournée.
 */
export function TourBoard({ tours, emptyMessage }: TourBoardProps) {
  const { t } = useTranslation()

  if (tours.length === 0) {
    return <p className="text-sm text-muted-foreground">{emptyMessage}</p>
  }

  return (
    <div className="overflow-x-auto pb-2">
      <ul className="flex min-w-fit gap-3">
        {tours.map((tour) => (
          <li
            key={tour.id}
            className="flex w-72 shrink-0 flex-col gap-3 rounded-lg border bg-card p-3"
          >
            <div className="flex items-start justify-between gap-2">
              <Link
                to={`/tours/${tour.id}`}
                className="min-w-0 font-medium text-primary hover:underline"
              >
                {tour.tourNumber}
              </Link>
              <StatusBadge status={tour.status} source="tour" />
            </div>

            {tour.tourDate === null ? null : (
              <p className="text-xs text-muted-foreground">{formatDate(tour.tourDate)}</p>
            )}

            <dl className="grid grid-cols-2 gap-2 text-xs">
              <Metric icon={Package} label={t('tours.fields.packages')} value={tour.totalPackages} />
              <Metric icon={Users} label={t('tours.fields.customers')} value={tour.totalCustomers} />
              <Metric
                icon={Weight}
                label={t('tours.fields.weightShort')}
                value={`${tour.totalWeight} kg`}
              />
              <Metric
                icon={Route}
                label={t('tours.fields.distance')}
                value={
                  tour.distanceMeters === 0
                    ? t('tours.notComputed')
                    : `${(tour.distanceMeters / 1000).toFixed(1)} km`
                }
              />
            </dl>

            <div className="flex flex-col gap-1 border-t pt-2">
              {(tour.stops ?? []).length === 0 ? (
                <p className="text-xs text-muted-foreground">{t('tours.noStop')}</p>
              ) : (
                (tour.stops ?? []).map((stop) => (
                  <div key={stop.id} className="flex items-start gap-2 rounded-md border px-2 py-1.5">
                    <span className="flex size-6 shrink-0 items-center justify-center rounded border text-xs font-medium">
                      {stop.sequence}
                    </span>
                    <span className="min-w-0 flex-1">
                      <span className="flex items-center gap-1 truncate text-xs">
                        <MapPin className="size-3 shrink-0 text-muted-foreground" aria-hidden />
                        {stop.addressLabel ?? stop.addressId}
                      </span>
                      <span className="flex items-center gap-2 text-[11px] text-muted-foreground">
                        <StatusBadge status={stop.status} source="tour_stop" />
                        {stop.serviceCount === undefined ? null : (
                          <span className="flex items-center gap-1">
                            <Clock className="size-3" aria-hidden />
                            {t('tours.serviceCount', { count: stop.serviceCount })}
                          </span>
                        )}
                      </span>
                    </span>
                  </div>
                ))
              )}
            </div>
          </li>
        ))}
      </ul>
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
    <div className="min-w-0">
      <dt className="flex items-center gap-1 text-[11px] text-muted-foreground">
        <Icon className="size-3" aria-hidden />
        {label}
      </dt>
      <dd className="truncate font-medium">{value}</dd>
    </div>
  )
}
