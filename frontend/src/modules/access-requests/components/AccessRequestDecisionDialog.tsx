import { Loader2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import type { AccessRequest } from '../types/accessRequest'
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
import { Textarea } from '@/shared/components/ui/textarea'

interface Props {
  request: AccessRequest | null
  decision: 'approve' | 'reject'
  onClose: () => void
  onConfirm: (note: string | undefined) => void
  isPending: boolean
}

/**
 * La confirmation d'une décision, avec son motif.
 *
 * **Un seul dialogue pour les deux décisions**, parce qu'elles se ressemblent
 * jusque dans leur conséquence : la demande est tranchée, et ne se reprend pas.
 * Ce qui change est ce que la confirmation annonce — une acceptation crée une
 * organisation et envoie un lien, un refus ne crée rien.
 *
 * Le motif reste facultatif : imposer une phrase à qui accepte ferait taper
 * « ok » cent fois, ce qui n'apprend rien à personne. Il est en revanche ce qui
 * rend un refus relisible six mois plus tard.
 */
export function AccessRequestDecisionDialog({
  request,
  decision,
  onClose,
  onConfirm,
  isPending,
}: Props) {
  const { t } = useTranslation()
  const [note, setNote] = useState('')

  const open = request !== null

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) {
          setNote('')
          onClose()
        }
      }}
    >
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{t(`accessRequests.${decision}.title`)}</DialogTitle>
          <DialogDescription>
            {t(`accessRequests.${decision}.description`, {
              company: request?.companyName ?? '',
              email: request?.email ?? '',
            })}
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-2">
          <Label htmlFor="decision-note">{t('accessRequests.note')}</Label>
          <Textarea
            id="decision-note"
            rows={3}
            value={note}
            onChange={(event) => setNote(event.target.value)}
            placeholder={t('accessRequests.notePlaceholder')}
          />
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose} disabled={isPending}>
            {t('common.cancel')}
          </Button>
          <Button
            variant={decision === 'reject' ? 'destructive' : 'default'}
            onClick={() => onConfirm(note.trim() || undefined)}
            disabled={isPending}
          >
            {isPending ? <Loader2 className="size-4 animate-spin" aria-hidden /> : null}
            {t(`accessRequests.${decision}.confirm`)}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
