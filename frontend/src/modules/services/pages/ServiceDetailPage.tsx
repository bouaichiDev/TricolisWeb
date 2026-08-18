import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { useDeleteService, useService } from '../hooks/useServices'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { DetailField } from '@/shared/components/layout/DetailField'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'

export function ServiceDetailPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams<{ id: string }>()
  const [confirmDelete, setConfirmDelete] = useState(false)

  const { data: service, isPending, error, refetch } = useService(id)
  const remove = useDeleteService()

  if (isPending) return <DetailSkeleton rows={4} />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!service) return null

  const yesNo = (value: boolean) => (value ? t('common.yes') : t('common.no'))

  return (
    <div className="flex flex-col gap-6">
      <EntityHeader
        title={service.name}
        subtitle={service.code}
        status={service.status}
        editTo={`/services/${id}/edit`}
        editPermission="services.update"
        onDelete={() => setConfirmDelete(true)}
        deletePermission="services.delete"
      />

      <SectionCard title={t('services.sections.general')}>
        <dl className="grid gap-x-8 sm:grid-cols-3">
          <DetailField label={t('services.fields.unit')}>{service.unit}</DetailField>
          <DetailField label={t('services.fields.defaultDurationMinutes')}>
            {service.defaultDurationMinutes === null
              ? null
              : t('services.minutes', { count: service.defaultDurationMinutes })}
          </DetailField>
        </dl>
      </SectionCard>

      {/* Ces quatre drapeaux décident de ce que le formulaire de commande
          exigera lorsque ce service y sera ajouté. */}
      <SectionCard
        title={t('services.sections.behaviour')}
        description={t('services.behaviourHint')}
      >
        <dl className="grid gap-x-8 sm:grid-cols-2">
          <DetailField label={t('services.fields.billableToCustomer')}>
            {yesNo(service.billableToCustomer)}
          </DetailField>
          <DetailField label={t('services.fields.payableToProvider')}>
            {yesNo(service.payableToProvider)}
          </DetailField>
          <DetailField label={t('services.fields.requiresAddress')}>
            {yesNo(service.requiresAddress)}
          </DetailField>
          <DetailField label={t('services.fields.requiresContact')}>
            {yesNo(service.requiresContact)}
          </DetailField>
        </dl>
      </SectionCard>

      <ConfirmDialog
        open={confirmDelete}
        onOpenChange={setConfirmDelete}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: service.name })}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          remove.mutate(id, {
            onSuccess: () => {
              setConfirmDelete(false)
              void navigate('/services')
            },
          })
        }}
      />
    </div>
  )
}
