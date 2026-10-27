import { Eye, Pencil, UserCheck, UserX } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'

import { useAuth } from '@/app/auth/useAuth'
import { paths } from '@/app/router/paths'
import { errorMessage } from '@/shared/api/errors'
import type { PortalUser } from '@/shared/api/types'
import { RowActions, type RowAction } from '@/shared/components/data/RowActions'

import { usersApi } from '../api/usersApi'
import { useUserMutation } from '../hooks/useUsers'
import { EditUserDialog } from './EditUserDialog'

/**
 * Les actions d'un membre dans la liste, en icônes : voir, modifier,
 * désactiver ou réactiver — chacune derrière sa permission. Comme sur la
 * fiche (`UserActions`), rien n'est proposé sur son propre compte ni sur le
 * propriétaire : le backend le refuserait (ManagedMemberGuard).
 */
export function UserRowActions({ member }: { member: PortalUser }) {
  const { context } = useAuth()
  const [editing, setEditing] = useState(false)
  const disable = useUserMutation(usersApi.disable)
  const enable = useUserMutation(usersApi.enable)
  const managed = context?.membership.id !== member.id && !member.isOwner

  const run = async (action: Promise<unknown>, success: string) => {
    try {
      await action
      toast.success(success)
    } catch (caught) {
      toast.error(errorMessage(caught))
    }
  }

  const disabled = member.status === 'disabled' || member.status === 'suspended'
  const confirmDisable = () => {
    if (window.confirm('Désactiver cet utilisateur ? Il perdra immédiatement l’accès au portail.')) void run(disable.mutateAsync(member.id), 'Utilisateur désactivé.')
  }

  const actions: RowAction[] = [{ key: 'view', icon: Eye, label: 'Consulter', to: paths.user(member.id) }]
  if (managed) {
    actions.push({ key: 'edit', icon: Pencil, label: 'Modifier', permission: 'customer.users.update', onClick: () => setEditing(true) })
    // Comme sur la fiche : un membre actif se désactive, un membre désactivé se
    // réactive ; une invitation en attente se renvoie depuis la fiche.
    if (disabled) actions.push({ key: 'enable', icon: UserCheck, label: 'Réactiver', permission: 'customer.users.disable', onClick: () => void run(enable.mutateAsync(member.id), 'Utilisateur réactivé.') })
    if (member.status === 'active') actions.push({ key: 'disable', icon: UserX, label: 'Désactiver', permission: 'customer.users.disable', tone: 'danger', onClick: confirmDisable })
  }

  return (
    <>
      <RowActions actions={actions} />
      {editing && <EditUserDialog member={member} open onOpenChange={setEditing} />}
    </>
  )
}
