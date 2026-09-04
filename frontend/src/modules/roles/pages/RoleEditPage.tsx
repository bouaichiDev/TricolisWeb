import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { RoleForm } from '../components/RoleForm'
import { useRole, useUpdateRole } from '../hooks/useRoles'
import { isEditableRole } from '../types/role'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { useAuth } from '@/shared/hooks/useAuth'

export function RoleEditPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams<{ id: string }>()
  const { membership } = useAuth()

  const { data: role, isPending, error, refetch } = useRole(id)
  const update = useUpdateRole(id)

  if (isPending) return <DetailSkeleton rows={4} />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!role) return null

  /**
   * Un rôle système ou plateforme n'est pas modifiable.
   *
   * L'écran l'annonce au lieu d'afficher un formulaire : le backend refuserait
   * l'enregistrement, et laisser saisir des valeurs perdues serait pire qu'un
   * message clair. La liste ne propose d'ailleurs pas le lien — cette page est
   * atteignable par l'URL, et c'est pour ce cas qu'elle est protégée.
   */
  if (!isEditableRole(role)) {
    return (
      <div className="flex flex-col gap-6">
        <PageHeader title={role.name} description={t('roles.readOnly')} />
        <Alert>
          <AlertDescription>{t('roles.systemLocked')}</AlertDescription>
        </Alert>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={role.name} description={t('roles.edit')} />

      {/* `code` est verrouillé : `UpdateRoleRequest` ne l'accepte pas — le
          modifier romprait les vérifications qui s'appuient dessus. */}
      <RoleForm
        lockCode
        organizationName={membership?.name}
        defaultValues={{
          code: role.code,
          name: role.name,
          status: role.status,
        }}
        defaultPermissionIds={(role.permissions ?? []).map((permission) => permission.id)}
        submitLabel={t('common.save')}
        onCancel={() => void navigate(`/roles/${id}`)}
        onSubmit={async (values, permissionIds) => {
          await update.mutateAsync({
            name: values.name.trim(),
            status: values.status,
            permissionIds,
          })
          void navigate(`/roles/${id}`)
        }}
      />
    </div>
  )
}
