import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { ApiError } from '@/shared/api/errors'
import { ControlledField } from '@/shared/components/form/ControlledField'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'

import { useSetMemberPassword } from '../hooks/useMembers'
import type { Member } from '../types/member'

interface MemberPasswordDialogProps {
  member: Member
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Poser un mot de passe pour un membre.
 *
 * **La confirmation n'est pas une formalité.** L'administrateur ne relira pas
 * le mot de passe une fois posé — l'API ne le rend jamais — et une coquille
 * enfermerait le membre dehors sans que personne ne sache pourquoi.
 *
 * L'écran prévient que les sessions ouvertes tombent : c'est le but, mais un
 * chauffeur déconnecté en pleine tournée sans qu'on l'ait dit est une surprise
 * qu'on ne veut pas.
 */
export function MemberPasswordDialog({ member, open, onOpenChange }: MemberPasswordDialogProps) {
  const { t } = useTranslation()

  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [error, setError] = useState<string | null>(null)

  const save = useSetMemberPassword()

  const mismatch = confirmation !== '' && password !== confirmation
  const incomplete = password === '' || mismatch

  const close = (next: boolean) => {
    if (!next) {
      // Rien ne survit a la fermeture : un mot de passe laisse dans un champ
      // se retrouverait pose sur le membre suivant.
      setPassword('')
      setConfirmation('')
      setError(null)
    }

    onOpenChange(next)
  }

  const submit = async () => {
    setError(null)

    try {
      await save.mutateAsync({ id: member.id, password })
      close(false)
    } catch (cause) {
      setError(cause instanceof ApiError ? remoteMessage(cause) : t('errors.unexpected'))
    }
  }

  return (
    <Dialog open={open} onOpenChange={close}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{t('users.password.setTitle')}</DialogTitle>
          <DialogDescription>{t('users.password.setHint')}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={error} />

        <div className="flex flex-col gap-4">
          <ControlledField
            label={t('users.password.field')}
            type="password"
            value={password}
            onChange={setPassword}
            required
            description={t('users.password.rules')}
          />

          <ControlledField
            label={t('users.password.confirmField')}
            type="password"
            value={confirmation}
            onChange={setConfirmation}
            required
            error={mismatch ? t('users.password.mismatch') : undefined}
          />

          <p className="text-xs text-muted-foreground">{t('users.password.revokesSessions')}</p>
        </div>

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => close(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="button" disabled={incomplete || save.isPending} onClick={() => void submit()}>
            {save.isPending ? t('common.saving') : t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

/**
 * Ce que le serveur reproche au mot de passe, en entier.
 *
 * Les règles de robustesse reviennent dans `errors.password` : les taire
 * laisserait l'administrateur deviner ce qui manque.
 */
function remoteMessage(error: ApiError): string {
  return error.errors.password?.join(' ') ?? error.message
}
