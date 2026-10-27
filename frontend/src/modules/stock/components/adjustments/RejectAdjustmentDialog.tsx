import { useState } from 'react'
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
import { Textarea } from '@/shared/components/ui/textarea'

import { DecisionError } from './DecisionError'
import { useRejectStockAdjustment } from '../../hooks/useStockAdjustmentRequests'

interface RejectAdjustmentDialogProps {
  requestId: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** Même borne que `RejectStockAdjustmentRequestRequest`. */
const MIN_ANSWER = 3

/**
 * Refuser : la réponse est obligatoire — le client doit savoir pourquoi.
 * Aucune écriture de stock.
 */
export function RejectAdjustmentDialog({ requestId, open, onOpenChange }: RejectAdjustmentDialogProps) {
  const { t } = useTranslation()
  const reject = useRejectStockAdjustment(requestId)
  const [comment, setComment] = useState('')
  const valid = comment.trim().length >= MIN_ANSWER

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{t('stock.adjustments.reject')}</DialogTitle>
          <DialogDescription>{t('stock.adjustments.rejectHint')}</DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-2">
          <Label htmlFor="adjustment-reject-comment">{t('stock.adjustments.answerRequired')}</Label>
          <Textarea
            id="adjustment-reject-comment"
            value={comment}
            maxLength={2000}
            required
            onChange={(event) => setComment(event.target.value)}
          />
          {valid ? null : (
            <p className="text-xs text-muted-foreground">{t('stock.adjustments.answerTooShort')}</p>
          )}
        </div>

        <DecisionError error={reject.error} />

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            type="button"
            variant="destructive"
            disabled={!valid || reject.isPending}
            onClick={() =>
              reject.mutate(
                { reviewComment: comment.trim() },
                { onSuccess: () => onOpenChange(false) },
              )
            }
          >
            {t('stock.adjustments.reject')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
