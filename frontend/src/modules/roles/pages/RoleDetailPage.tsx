import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { RolePermissionsPanel } from '../components/RolePermissionsPanel'
import { useDeleteRole, useRole } from '../hooks/useRoles'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { DetailField } from '@/shared/components/layout/DetailField'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'

export function RoleDetailPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams<{ id: string }>()
  const [confirmDelete, setConfirmDelete] = useState(false)

  const { data: role, isPending, error, refetch } = useRole(id)
  const remove = useDeleteRole()

  if (isPending) return <DetailSkeleton rows={4} />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!role) return null

  return (
    <div className="flex flex-col gap-6">
      {/* Un rôle système ne propose pas la suppression : il est livré par le
          seeder et le retirer casserait les comptes qui s'en servent. */}
      <EntityHeader
        title={role.name}
        subtitle={role.code}
        status={role.status}
        editTo={`/roles/${id}/edit`}
        editPermission="roles.update"
        onDelete={role.isSystem ? undefined : () => setConfirmDelete(true)}
        deletePermission="roles.delete"
      />

      <SectionCard title={t('roles.sections.general')}>
        <dl className="grid gap-x-8 sm:grid-cols-3">
          <DetailField label={t('roles.fields.scope')}>{role.scope}</DetailField>
          <DetailField label={t('roles.fields.isSystem')}>
            {role.isSystem ? t('common.yes') : t('common.no')}
          </DetailField>
          <DetailField label={t('roles.permissionsTotal')}>
            {role.permissions?.length ?? 0}
          </DetailField>
        </dl>
      </SectionCard>

      <SectionCard title={t('roles.sections.permissions')}>
        <RolePermissionsPanel permissions={role.permissions ?? []} />
      </SectionCard>

      <ConfirmDialog
        open={confirmDelete}
        onOpenChange={setConfirmDelete}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: role.name })}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          remove.mutate(id, {
            onSuccess: () => {
              setConfirmDelete(false)
              void navigate('/roles')
            },
          })
        }}
      />
    </div>
  )
}
