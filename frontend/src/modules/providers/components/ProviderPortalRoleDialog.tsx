import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'
import { Label } from '@/shared/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/components/ui/select'

import type { ProviderPortalRole, ProviderPortalUser } from '../types/providerPortalUser'

interface Props {
  member: ProviderPortalUser | null
  roles: ProviderPortalRole[]
  isPending: boolean
  onClose: () => void
  onSubmit: (roleId: string) => void
}

/**
 * Changer le rôle d'un accès au portail fournisseur. Un accès porte un seul
 * rôle ; seuls les rôles de portée fournisseur sont proposés.
 */
export function ProviderPortalRoleDialog({ member, roles, isPending, onClose, onSubmit }: Props) {
  const { t } = useTranslation()
  const [roleId, setRoleId] = useState('')

  useEffect(() => {
    setRoleId(member?.roles[0]?.id ?? '')
  }, [member])

  return (
    <Dialog open={member !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t('providers.portal.changeRole')}</DialogTitle>
          <DialogDescription>{member?.user.email}</DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-2">
          <Label htmlFor="provider-portal-role">{t('providers.portal.fields.role')}</Label>
          <Select value={roleId} onValueChange={setRoleId}>
            <SelectTrigger id="provider-portal-role">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {roles.map((role) => (
                <SelectItem key={role.id} value={role.id}>
                  {role.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="button" disabled={roleId === '' || isPending} onClick={() => onSubmit(roleId)}>
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
