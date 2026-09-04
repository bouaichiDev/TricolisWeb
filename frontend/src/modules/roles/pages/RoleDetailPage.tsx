import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { MenuSettingsPanel } from '@/modules/menu/components/MenuSettingsPanel'
import { RolePermissionsPanel } from '../components/RolePermissionsPanel'
import { useDeleteRole, useRole } from '../hooks/useRoles'
import { isEditableRole, isMenuEditableRole } from '../types/role'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { DetailField } from '@/shared/components/layout/DetailField'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Badge } from '@/shared/components/ui/badge'

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

  const editable = isEditableRole(role)

  return (
    <div className="flex flex-col gap-6">
      {/* Un rôle système ou plateforme n'offre ni modification ni suppression :
          il est livré avec l'application, et les comptes qui s'en servent
          dépendent de son contenu. Seule la suppression était masquée avant
          cette correction — le lien de modification restait proposé, pour un
          refus certain. */}
      <EntityHeader
        title={role.name}
        subtitle={role.code}
        status={role.status}
        editTo={editable ? `/roles/${id}/edit` : undefined}
        editPermission={editable ? 'roles.update' : undefined}
        onDelete={editable ? () => setConfirmDelete(true) : undefined}
        deletePermission="roles.delete"
        actions={
          role.isSystem ? (
            <Badge variant="secondary" className="self-center font-normal">
              {t('roles.readOnly')}
            </Badge>
          ) : null
        }
      />

      <SectionCard title={t('roles.sections.general')}>
        <dl className="grid gap-x-8 sm:grid-cols-3">
          <DetailField label={t('roles.fields.scope')}>
            {role.scope === 'platform' ? t('roles.platform') : t('roles.scopeOrganization')}
          </DetailField>
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

      {/* Les permissions disent ce que le rôle a le droit d’ouvrir ; le menu dit
          ce qu’il a besoin de voir. Les deux se règlent sur la même page parce
          qu’on les pense ensemble — un rôle qui gagne une permission veut
          souvent l’entrée qui va avec. Le menu ne les remplace pas : masquer
          une entrée ne retire aucun droit, l’écran reste atteignable par son
          adresse. */}
      <SectionCard title={t('roles.sections.menu')}>
        <MenuSettingsPanel roleId={id} editable={isMenuEditableRole(role)} />
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
