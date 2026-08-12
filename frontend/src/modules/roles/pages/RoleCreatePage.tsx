import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'

import { RoleForm } from '../components/RoleForm'
import { useCreateRole } from '../hooks/useRoles'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { useAuth } from '@/shared/hooks/useAuth'

/**
 * Création d'un rôle.
 *
 * Ni la portée, ni le drapeau système, ni l'organisation ne sont transmis :
 * l'API les impose. Un rôle créé ici s'applique à l'organisation active, n'est
 * pas système, et ne confère aucune autorité plateforme — quel que soit le nom
 * qu'on lui donne.
 */
export function RoleCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { membership } = useAuth()
  const create = useCreateRole()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('roles.create')} description={t('roles.subtitle')} />

      <RoleForm
        organizationName={membership?.name}
        submitLabel={t('common.create')}
        onCancel={() => void navigate('/roles')}
        onSubmit={async (values, permissionIds) => {
          const role = await create.mutateAsync({
            code: values.code.trim(),
            name: values.name.trim(),
            status: values.status,
            permissionIds,
          })
          void navigate(`/roles/${role.id}`)
        }}
      />
    </div>
  )
}
