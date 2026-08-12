import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'

import { RoleForm } from '../components/RoleForm'
import { useCreateRole } from '../hooks/useRoles'
import { scopeOrNull } from '../schemas/roleSchema'
import { PageHeader } from '@/shared/components/layout/PageHeader'

export function RoleCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateRole()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('roles.create')} description={t('roles.subtitle')} />

      {/* `isSystem: false` : un rôle système est livré par le seeder, il ne se
          crée pas depuis l'interface. */}
      <RoleForm
        submitLabel={t('common.create')}
        onCancel={() => void navigate('/roles')}
        onSubmit={async (values, permissionIds) => {
          const role = await create.mutateAsync({
            code: values.code.trim(),
            name: values.name.trim(),
            scope: scopeOrNull(values.scope),
            isSystem: false,
            status: values.status,
            permissionIds,
          })
          void navigate(`/roles/${role.id}`)
        }}
      />
    </div>
  )
}
