import { useTranslation } from 'react-i18next'
import { useParams } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { useStatusOptions } from '@/modules/statuses/hooks/useStatuses'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { formatDate } from '@/shared/utils/format'

import { useChangeTourStatus, useTour } from '../hooks/useTours'

/**
 * Fiche d'une tournée.
 *
 * **Les passages proposés viennent du référentiel**, pas d'une liste écrite
 * ici : c'est `status_transitions` qui dit ce qu'une tournée peut devenir, et
 * le serveur refuserait tout le reste. Proposer un bouton que le serveur
 * rejette serait une promesse en l'air.
 */
export function TourDetailPage() {
  const { t } = useTranslation()
  const { id = '' } = useParams()

  const { data: tour, isPending, error, refetch } = useTour(id)
  const change = useChangeTourStatus()
  const { statuses } = useStatusOptions('tour', tour?.status)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!tour) return null

  // Les autres etats actifs : c'est le serveur qui tranchera lesquels sont
  // atteignables depuis celui-ci.
  const targets = statuses.filter((status) => status.code !== tour.status)

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={tour.tourNumber}
        description={tour.tourDate === null ? undefined : formatDate(tour.tourDate)}
        actions={
          <PermissionGuard permission="tours.update">
            <span className="flex flex-wrap gap-2">
              {targets.map((status) => (
                <Button
                  key={status.id}
                  type="button"
                  variant="outline"
                  size="sm"
                  disabled={change.isPending}
                  onClick={() => change.mutate({ id: tour.id, status: status.code })}
                >
                  {status.label}
                </Button>
              ))}
            </span>
          </PermissionGuard>
        }
      />

      <SectionCard title={t('tours.sections.summary')}>
        <dl className="grid gap-4 sm:grid-cols-3">
          <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('tours.fields.status')}
            </dt>
            <dd className="mt-1">
              <StatusBadge status={tour.status} source="tour" />
            </dd>
          </div>

          <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('tours.fields.load')}
            </dt>
            <dd className="mt-1 text-sm">
              {t('tours.loadSummary', {
                packages: tour.totalPackages,
                customers: tour.totalCustomers,
              })}
            </dd>
          </div>

          <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('tours.fields.weight')}
            </dt>
            <dd className="mt-1 text-sm">
              {tour.totalWeight} kg · {tour.totalVolume} m³
            </dd>
          </div>

          <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('tours.fields.distance')}
            </dt>
            <dd className="mt-1 text-sm">
              {/* Zero tant que l'itineraire n'a pas ete calcule : le dire vaut
                  mieux qu'afficher « 0 km » comme une mesure. */}
              {tour.distanceMeters === 0
                ? t('tours.notComputed')
                : `${(tour.distanceMeters / 1000).toFixed(1)} km`}
            </dd>
          </div>

          <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('tours.fields.drivingTime')}
            </dt>
            <dd className="mt-1 text-sm">
              {tour.drivingTimeMinutes === 0
                ? t('tours.notComputed')
                : t('tours.minutes', { count: tour.drivingTimeMinutes })}
            </dd>
          </div>

          {tour.instructions === null ? null : (
            <div className="sm:col-span-3">
              <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {t('tours.fields.instructions')}
              </dt>
              <dd className="mt-1 whitespace-pre-wrap text-sm">{tour.instructions}</dd>
            </div>
          )}
        </dl>
      </SectionCard>
    </div>
  )
}
