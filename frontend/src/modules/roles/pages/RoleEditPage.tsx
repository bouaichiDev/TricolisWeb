import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { RoleForm } from '../components/RoleForm'
import { useRole, useUpdateRole } from '../hooks/useRoles'
import { scopeOrNull } from '../schemas/roleSchema'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'

export function RoleEditPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams<{ id: string }>()

  const { data: role, isPending, error, refetch } = useRole(id)
  const update = useUpdateRole(id)

  if (isPending) return <DetailSkeleton rows={4} />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!role) return null

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={role.name} description={t('roles.edit')} />

      {/* `code` est verrouillé : `UpdateRoleRequest` ne l'accepte pas — le
          modifier romprait les vérifications qui s'appuient dessus. */}
      <RoleForm
        lockCode
        defaultValues={{
          code: role.code,
          name: role.name,
          scope: role.scope ?? '',
          status: role.status,
        }}
        defaultPermissionIds={(role.permissions ?? []).map((permission) => permission.id)}
        submitLabel={t('common.save')}
        onCancel={() => void navigate(`/roles/${id}`)}
        onSubmit={async (values, permissionIds) => {
          await update.mutateAsync({
            name: values.name.trim(),
            scope: scopeOrNull(values.scope),
            status: values.status,
            permissionIds,
          })
          void navigate(`/roles/${id}`)
        }}
      />
    </div>
  )
}
