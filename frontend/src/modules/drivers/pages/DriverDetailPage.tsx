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

import { useDriver } from '../hooks/useDrivers'

/** Fiche d'un chauffeur. Disponibilités et compétences relèvent d'autres phases. */
export function DriverDetailPage() {
  const { t } = useTranslation()
  const { id = '' } = useParams()

  const { data: driver, isPending, error, refetch } = useDriver(id)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!driver) return null

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={driver.name}
        description={driver.code}
        actions={
          <PermissionGuard permission="drivers.update">
            <Button asChild variant="outline">
              <Link to={`/drivers/${driver.id}/edit`}>
                <Pencil className="size-4" aria-hidden />
                {t('common.edit')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <SectionCard title={t('drivers.identity')}>
        <dl className="grid gap-4 sm:grid-cols-2">
          <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('drivers.fields.code')}
            </dt>
            <dd className="mt-1 text-sm">{driver.code}</dd>
          </div>
          <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('drivers.fields.provider')}
            </dt>
            <dd className="mt-1 text-sm">
              <Link to={`/providers/${driver.providerId}`} className="text-primary hover:underline">
                {driver.providerName ?? driver.providerId}
              </Link>
            </dd>
          </div>
          <div>
            <dt className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('drivers.fields.status')}
            </dt>
            <dd className="mt-1">
              <StatusBadge status={driver.status} source="driver" />
            </dd>
          </div>
        </dl>
      </SectionCard>
    </div>
  )
}
