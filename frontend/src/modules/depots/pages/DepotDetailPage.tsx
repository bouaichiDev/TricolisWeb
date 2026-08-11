import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { useDeleteDepot, useDepot } from '../hooks/useDepots'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { DetailField } from '@/shared/components/layout/DetailField'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { formatDateTime } from '@/shared/utils/format'

export function DepotDetailPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { agencyId = '', depotId = '' } = useParams<{ agencyId: string; depotId: string }>()
  const [confirmDelete, setConfirmDelete] = useState(false)

  const { data: depot, isPending, error, refetch } = useDepot(agencyId, depotId)
  const remove = useDeleteDepot(agencyId)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!depot) return null

  return (
    <div className="flex flex-col gap-6">
      <EntityHeader
        title={depot.name}
        subtitle={depot.code}
        status={depot.status}
        editTo={`/agencies/${agencyId}/depots/${depotId}/edit`}
        editPermission="depots.update"
        onDelete={() => setConfirmDelete(true)}
        deletePermission="depots.delete"
      />

      <SectionCard title={t('depots.sections.general')}>
        <dl className="grid gap-x-8 sm:grid-cols-2">
          <DetailField label={t('depots.fields.code')}>{depot.code}</DetailField>
          <DetailField label={t('depots.fields.name')}>{depot.name}</DetailField>
          <DetailField label={t('common.createdAt')}>{formatDateTime(depot.createdAt)}</DetailField>
          <DetailField label={t('common.updatedAt')}>{formatDateTime(depot.updatedAt)}</DetailField>
        </dl>
      </SectionCard>

      <ConfirmDialog
        open={confirmDelete}
        onOpenChange={setConfirmDelete}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: depot.name })}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          remove.mutate(depotId, {
            onSuccess: () => {
              setConfirmDelete(false)
              void navigate(`/agencies/${agencyId}`)
            },
          })
        }}
      />
    </div>
  )
}
