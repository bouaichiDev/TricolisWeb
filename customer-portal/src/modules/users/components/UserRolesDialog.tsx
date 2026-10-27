import { useState } from 'react'
import { toast } from 'sonner'

import { ApiError, errorMessage } from '@/shared/api/errors'
import type { PortalUser } from '@/shared/api/types'
import { InlineLoader } from '@/shared/components/feedback/States'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Checkbox } from '@/shared/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog'
import { Label } from '@/shared/components/ui/label'

import { usersApi } from '../api/usersApi'
import { useRoles, useUserMutation } from '../hooks/useUsers'

interface Props {
  member: PortalUser
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Le jeu de rôles envoyé remplace l'ancien. Un rôle portant des droits que
 * l'on n'a pas soi-même est refusé par le backend (plafond de délégation).
 */
export function UserRolesDialog({ member, open, onOpenChange }: Props) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Rôles</DialogTitle>
          <DialogDescription>Les permissions de l’utilisateur sont l’union de ses rôles.</DialogDescription>
        </DialogHeader>
        {/* Monté à chaque ouverture : la sélection repart des rôles actuels. */}
        {open && <RolesEditor member={member} onDone={() => onOpenChange(false)} />}
      </DialogContent>
    </Dialog>
  )
}

function RolesEditor({ member, onDone }: { member: PortalUser; onDone: () => void }) {
  const roles = useRoles()
  const assign = useUserMutation((roleIds: string[]) => usersApi.assignRoles(member.id, roleIds))
  const [selected, setSelected] = useState<string[]>(() => member.roles.map((role) => role.id))
  const [error, setError] = useState<string | null>(null)

  const toggle = (id: string, checked: boolean) =>
    setSelected((current) => (checked ? [...current, id] : current.filter((value) => value !== id)))

  const save = async () => {
    setError(null)

    try {
      await assign.mutateAsync(selected)
      toast.success('Rôles mis à jour.')
      onDone()
    } catch (caught) {
      const detail = caught instanceof ApiError ? (caught.fieldError('roleIds') ?? caught.fieldError('member')) : undefined
      setError(detail ?? errorMessage(caught))
    }
  }

  return (
    <>
      {error && (
        <Alert variant="destructive" role="alert">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}
      {roles.isPending && <InlineLoader />}
      <div className="space-y-3">
        {roles.data?.map((role) => (
          <div key={role.id} className="flex items-start gap-3">
            <Checkbox
              id={`role-${role.id}`}
              checked={selected.includes(role.id)}
              onCheckedChange={(checked) => toggle(role.id, checked === true)}
            />
            <Label htmlFor={`role-${role.id}`} className="grid gap-0.5 font-normal">
              <span className="font-medium">{role.name}</span>
              <span className="text-muted-foreground text-xs">{role.permissions.length} permissions</span>
            </Label>
          </div>
        ))}
      </div>
      <DialogFooter>
        <Button variant="outline" onClick={onDone}>
          Annuler
        </Button>
        <Button onClick={() => void save()} disabled={assign.isPending}>
          Enregistrer
        </Button>
      </DialogFooter>
    </>
  )
}
