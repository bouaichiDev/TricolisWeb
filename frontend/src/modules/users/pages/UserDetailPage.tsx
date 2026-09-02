import { UserX } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'

import { useDisableMember, useMember } from '../hooks/useMembers'
import { memberFullName } from '../types/member'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { MemberAccessCard } from '../components/MemberAccessCard'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { DetailField } from '@/shared/components/layout/DetailField'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import { formatDateTime } from '@/shared/utils/format'

/**
 * Fiche d'un membre.
 *
 * Le bouton propose « désactiver », pas « supprimer » : c'est ce que fait
 * réellement `DELETE /organization-users/{id}`, le rattachement étant référencé
 * par l'audit. Écrire « supprimer » promettrait autre chose.
 */
export function UserDetailPage() {
  const { t } = useTranslation()
  const { id = '' } = useParams<{ id: string }>()
  const [confirmDisable, setConfirmDisable] = useState(false)

  const { data: member, isPending, error, refetch } = useMember(id)
  const disable = useDisableMember()

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!member) return null

  const alreadyDisabled = member.status === 'disabled'

  return (
    <div className="flex flex-col gap-6">
      <EntityHeader
        title={memberFullName(member)}
        subtitle={member.user.email}
        status={member.status}
        editTo={`/users/${id}/edit`}
        editPermission="users.update"
        actions={
          alreadyDisabled ? null : (
            <PermissionGuard permission="users.disable">
              <Button variant="outline" onClick={() => setConfirmDisable(true)}>
                <UserX className="size-4" aria-hidden />
                {t('users.disable')}
              </Button>
            </PermissionGuard>
          )
        }
      />

      {/* Rendre l'acces avant de decrire l'identite : c'est ce qu'on vient
          faire sur cette fiche quand quelqu'un appelle, bloque dehors. */}
      <MemberAccessCard member={member} />

      <SectionCard title={t('users.sections.identity')}>
        <dl className="grid gap-x-8 sm:grid-cols-2">
          <DetailField label={t('users.fields.phone')}>{member.user.phone}</DetailField>
          <DetailField label={t('users.fields.preferredLanguage')}>
            {member.user.preferredLanguage}
          </DetailField>
          <DetailField label={t('users.fields.isOwner')}>
            {member.isOwner ? t('common.yes') : t('common.no')}
          </DetailField>
          <DetailField label={t('users.fields.isPrimary')}>
            {member.isPrimary ? t('common.yes') : t('common.no')}
          </DetailField>
          <DetailField label={t('users.fields.joinedAt')}>
            {formatDateTime(member.joinedAt)}
          </DetailField>
        </dl>
      </SectionCard>

      {member.driver === null ? null : (
        <SectionCard title={t('users.sections.driver')}>
          <p className="text-sm">
            <Link to={`/drivers/${member.driver.id}`} className="text-primary hover:underline">
              {member.driver.name}
            </Link>{' '}
            <span className="text-muted-foreground">· {member.driver.code}</span>
          </p>
        </SectionCard>
      )}

      <SectionCard title={t('users.sections.roles')}>
        {member.roles.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t('users.noAssignedRoles')}</p>
        ) : (
          <div className="flex flex-wrap gap-2">
            {member.roles.map((role) => (
              <Badge key={role.id} variant="secondary" className="font-normal">
                {role.name}
              </Badge>
            ))}
          </div>
        )}
      </SectionCard>

      <ConfirmDialog
        open={confirmDisable}
        onOpenChange={setConfirmDisable}
        title={t('users.disable')}
        description={t('users.disableConfirm', { name: memberFullName(member) })}
        confirmLabel={t('users.disable')}
        isPending={disable.isPending}
        onConfirm={() => {
          disable.mutate(id, {
            onSuccess: () => {
              setConfirmDisable(false)
              void refetch()
            },
          })
        }}
      />
    </div>
  )
}
