import { useState } from 'react'
import { toast } from 'sonner'

import { Can } from '@/app/guards/Can'
import { errorMessage } from '@/shared/api/errors'
import type { PortalUser } from '@/shared/api/types'
import { Button } from '@/shared/components/ui/button'

import { usersApi } from '../api/usersApi'
import { useUserMutation } from '../hooks/useUsers'
import { EditUserDialog } from './EditUserDialog'
import { UserRolesDialog } from './UserRolesDialog'

/**
 * Actions sur un membre, chacune derrière sa permission.
 *
 * Rien n'est proposé sur son propre compte ni sur le propriétaire : le
 * backend les refuserait (§ ManagedMemberGuard), inutile de tendre le bouton.
 */
export function UserActions({ member, isSelf }: { member: PortalUser; isSelf: boolean }) {
  const [editing, setEditing] = useState(false)
  const [assigning, setAssigning] = useState(false)
  const disable = useUserMutation(usersApi.disable)
  const enable = useUserMutation(usersApi.enable)
  const resend = useUserMutation(usersApi.resendInvitation)

  if (isSelf || member.isOwner) return null

  const run = async (action: Promise<unknown>, success: string) => {
    try {
      await action
      toast.success(success)
    } catch (caught) {
      toast.error(errorMessage(caught))
    }
  }

  const disabled = member.status === 'disabled' || member.status === 'suspended'

  return (
    <>
      <Can permission="customer.users.update">
        <Button variant="outline" onClick={() => setEditing(true)}>
          Modifier
        </Button>
      </Can>
      <Can permission={['customer.users.assign_role', 'customer.roles.view']}>
        <Button variant="outline" onClick={() => setAssigning(true)}>
          Rôles
        </Button>
      </Can>
      {member.status === 'invited' && (
        <Can permission="customer.users.invite">
          <Button variant="outline" onClick={() => void run(resend.mutateAsync(member.id), 'Invitation renvoyée.')}>
            Renvoyer l’invitation
          </Button>
        </Can>
      )}
      <Can permission="customer.users.disable">
        {disabled ? (
          <Button onClick={() => void run(enable.mutateAsync(member.id), 'Utilisateur réactivé.')}>Réactiver</Button>
        ) : (
          member.status === 'active' && (
            <Button
              variant="destructive"
              onClick={() => {
                if (window.confirm('Désactiver cet utilisateur ? Il perdra immédiatement l’accès au portail.')) {
                  void run(disable.mutateAsync(member.id), 'Utilisateur désactivé.')
                }
              }}
            >
              Désactiver
            </Button>
          )
        )}
      </Can>
      <EditUserDialog member={member} open={editing} onOpenChange={setEditing} />
      <UserRolesDialog member={member} open={assigning} onOpenChange={setAssigning} />
    </>
  )
}
