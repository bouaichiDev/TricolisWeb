import { Pencil } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'

import { VehicleCapacitySummary } from '../components/VehicleCapacitySummary'
import { useVehicle } from '../hooks/useVehicles'

/** Fiche d'un véhicule. Maintenance et disponibilités relèvent d'autres phases. */
export function VehicleDetailPage() {
  const { t } = useTranslation()
  const { id = '' } = useParams()

  const { data: vehicle, isPending, error, refetch } = useVehicle(id)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!vehicle) return null

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={vehicle.code}
        description={vehicle.registrationNumber}
        actions={
          <PermissionGuard permission="vehicles.update">
            <Button asChild variant="outline">
              <Link to={`/vehicles/${vehicle.id}/edit`}>
                <Pencil className="size-4" aria-hidden />
                {t('common.edit')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <SectionCard title={t('vehicles.identity')}>
        <dl className="grid gap-4 sm:grid-cols-2">
          <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('vehicles.fields.provider')}
            </dt>
            <dd className="mt-1 text-sm">
              <Link
                to={`/providers/${vehicle.providerId}`}
                className="text-primary hover:underline"
              >
                {vehicle.providerName ?? vehicle.providerId}
              </Link>
            </dd>
          </div>
          <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('vehicles.fields.vehicleType')}
            </dt>
            <dd className="mt-1 text-sm">{vehicle.vehicleTypeName ?? '—'}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('vehicles.fields.status')}
            </dt>
            <dd className="mt-1">
              <StatusBadge status={vehicle.status} source="vehicle" />
            </dd>
          </div>
        </dl>
      </SectionCard>

      <VehicleCapacitySummary vehicle={vehicle} />
    </div>
  )
}
