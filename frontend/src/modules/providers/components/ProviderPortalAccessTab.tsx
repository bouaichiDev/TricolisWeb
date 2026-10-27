import { MailPlus, Send, UserCheck, UserCog, UserX } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { RowActions, type RowAction } from '@/shared/components/data/RowActions'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

import {
  useChangeProviderPortalRole,
  useInviteProviderPortalUser,
  useProviderPortalRoles,
  useProviderPortalUserAction,
  useProviderPortalUsers,
} from '../hooks/useProviderPortalUsers'
import type { ProviderPortalUser } from '../types/providerPortalUser'
import { ProviderPortalInviteDialog } from './ProviderPortalInviteDialog'
import { ProviderPortalRoleDialog } from './ProviderPortalRoleDialog'

/**
 * Accès au portail du fournisseur.
 *
 * C'est ici, et nulle part ailleurs, qu'on ouvre ou ferme le portail d'un
 * fournisseur à un compte : le portail lui-même ne gère ni comptes ni rôles.
 * Chaque action n'est offerte que dans l'état où elle a un sens — désactiver
 * un accès actif ou une invitation envoyée par erreur, réactiver un accès
 * désactivé, renvoyer une invitation en attente.
 */
export function ProviderPortalAccessTab({ providerId, active }: { providerId: string; active: boolean }) {
  const { t } = useTranslation()
  const [inviting, setInviting] = useState(false)
  const [toDisable, setToDisable] = useState<ProviderPortalUser | null>(null)
  const [toRole, setToRole] = useState<ProviderPortalUser | null>(null)

  const members = useProviderPortalUsers(providerId, active)
  const roles = useProviderPortalRoles(providerId, active)
  const invite = useInviteProviderPortalUser(providerId)
  const act = useProviderPortalUserAction(providerId)
  const changeRole = useChangeProviderPortalRole(providerId)

  const columns: Column<ProviderPortalUser>[] = [
    {
      key: 'name',
      header: t('providers.portal.fields.name'),
      cell: (row) => `${row.user.firstName ?? ''} ${row.user.lastName ?? ''}`.trim() || '—',
    },
    { key: 'email', header: t('providers.portal.fields.email'), cell: (row) => row.user.email },
    {
      key: 'role',
      header: t('providers.portal.fields.role'),
      hideOnMobile: true,
      cell: (row) => row.roles.map((role) => role.name).join(', ') || '—',
    },
    {
      key: 'status',
      header: t('providers.portal.fields.status'),
      cell: (row) => <Badge variant={row.status === 'active' ? 'default' : 'secondary'}>{row.statusLabel}</Badge>,
    },
  ]

  const actionsOf = (row: ProviderPortalUser): RowAction[] => {
    const run = (action: 'enable' | 'resend-invitation') => () => act.mutate({ memberId: row.id, action })
    const all: (RowAction | false)[] = [
      { key: 'role', icon: UserCog, label: t('providers.portal.changeRole'), onClick: () => setToRole(row) },
      (row.status === 'active' || row.status === 'invited') && { key: 'disable', icon: UserX, label: t('providers.portal.disable'), tone: 'danger', onClick: () => setToDisable(row) },
      row.status === 'disabled' && { key: 'enable', icon: UserCheck, label: t('providers.portal.enable'), onClick: run('enable') },
      row.status === 'invited' && { key: 'resend', icon: Send, label: t('providers.portal.resend'), onClick: run('resend-invitation') },
    ]

    return all.filter((action): action is RowAction => action !== false).map((action) => ({ ...action, permission: 'providers.update' }))
  }

  return (
    <div className="flex flex-col gap-4">
      <PermissionGuard permission="providers.update">
        <div className="flex justify-end">
          <Button size="sm" onClick={() => setInviting(true)} disabled={!roles.data}>
            <MailPlus className="size-4" aria-hidden />
            {t('providers.portal.invite')}
          </Button>
        </div>
      </PermissionGuard>

      <DataTable
        columns={columns}
        rows={members.data ?? []}
        rowKey={(row) => row.id}
        isLoading={members.isPending}
        error={members.error}
        onRetry={() => void members.refetch()}
        actions={(row) => <RowActions actions={actionsOf(row)} />}
        emptyMessage={t('providers.portal.empty')}
      />

      <ProviderPortalInviteDialog
        open={inviting}
        onOpenChange={setInviting}
        roles={roles.data ?? []}
        onSubmit={(values) => invite.mutateAsync(values)}
      />

      <ProviderPortalRoleDialog
        member={toRole}
        roles={roles.data ?? []}
        isPending={changeRole.isPending}
        onClose={() => setToRole(null)}
        onSubmit={(roleId) => {
          if (toRole) changeRole.mutate({ memberId: toRole.id, roleId }, { onSuccess: () => setToRole(null) })
        }}
      />

      <ConfirmDialog
        open={toDisable !== null}
        onOpenChange={(open) => !open && setToDisable(null)}
        title={t('providers.portal.disableTitle')}
        description={t('providers.portal.disableDescription', { email: toDisable?.user.email ?? '' })}
        confirmLabel={t('providers.portal.disable')}
        isPending={act.isPending}
        onConfirm={() => {
          if (toDisable) act.mutate({ memberId: toDisable.id, action: 'disable' }, { onSettled: () => setToDisable(null) })
        }}
      />
    </div>
  )
}
