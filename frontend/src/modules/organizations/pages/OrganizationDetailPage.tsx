import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { useDeleteOrganization, useOrganization } from '../hooks/useOrganizations'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { DetailField } from '@/shared/components/layout/DetailField'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { formatDateTime } from '@/shared/utils/format'

export function OrganizationDetailPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams<{ id: string }>()
  const [confirmDelete, setConfirmDelete] = useState(false)

  const { data: organization, isPending, error, refetch } = useOrganization(id)
  const remove = useDeleteOrganization()

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!organization) return null

  return (
    <div className="flex flex-col gap-6">
      <EntityHeader
        title={organization.name}
        subtitle={organization.code}
        status={organization.status}
        editTo={`/organizations/${id}/edit`}
        editPermission="organizations.update"
        onDelete={() => setConfirmDelete(true)}
        deletePermission="organizations.delete"
      />

      <SectionCard title={t('organizations.sections.identity')}>
        <dl className="grid gap-x-8 sm:grid-cols-2">
          <DetailField label={t('organizations.fields.legalName')}>
            {organization.legalName}
          </DetailField>
          <DetailField label={t('organizations.fields.registrationNumber')}>
            {organization.registrationNumber}
          </DetailField>
          <DetailField label={t('organizations.fields.taxNumber')}>
            {organization.taxNumber}
          </DetailField>
          <DetailField label={t('organizations.fields.email')}>{organization.email}</DetailField>
          <DetailField label={t('organizations.fields.phone')}>{organization.phone}</DetailField>
        </dl>
      </SectionCard>

      <SectionCard title={t('organizations.sections.preferences')}>
        <dl className="grid gap-x-8 sm:grid-cols-3">
          <DetailField label={t('organizations.fields.preferredLanguage')}>
            {organization.preferredLanguage}
          </DetailField>
          <DetailField label={t('organizations.fields.timezone')}>
            {organization.timezone}
          </DetailField>
          <DetailField label={t('organizations.fields.currencyCode')}>
            {organization.currencyCode}
          </DetailField>
          <DetailField label={t('common.createdAt')}>
            {formatDateTime(organization.createdAt)}
          </DetailField>
          <DetailField label={t('common.updatedAt')}>
            {formatDateTime(organization.updatedAt)}
          </DetailField>
        </dl>
      </SectionCard>

      <ConfirmDialog
        open={confirmDelete}
        onOpenChange={setConfirmDelete}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: organization.name })}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          remove.mutate(id, {
            onSuccess: () => {
              setConfirmDelete(false)
              void navigate('/organizations')
            },
          })
        }}
      />
    </div>
  )
}
