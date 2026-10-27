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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/components/ui/select'
import { Textarea } from '@/shared/components/ui/textarea'

import { CorrectionRecap } from './CorrectionRecap'
import { DecisionError } from './DecisionError'
import { useApproveStockAdjustment } from '../../hooks/useStockAdjustmentRequests'
import {
  formatAdjustmentLocation,
  type StockAdjustmentRequestDetail,
} from '../../types/stockAdjustment'

interface ApproveAdjustmentDialogProps {
  request: StockAdjustmentRequestDetail
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Accepter : récapitule le mouvement que le serveur créera.
 *
 * Une demande « tout l'article » exige de choisir l'emplacement à corriger,
 * parmi ceux où l'article a une balance — ceux que `corrections` énumère.
 * Une demande qui porte déjà un emplacement n'en propose aucun : le serveur
 * refuserait le choix.
 */
export function ApproveAdjustmentDialog({
  request,
  open,
  onOpenChange,
}: ApproveAdjustmentDialogProps) {
  const { t } = useTranslation()
  const approve = useApproveStockAdjustment(request.id)
  const wholeItem = request.stockLocationId === null
  const [locationId, setLocationId] = useState<string | undefined>(undefined)
  const [comment, setComment] = useState('')

  const correction = wholeItem
    ? request.corrections.find((entry) => entry.location.id === locationId)
    : request.corrections[0]

  const submit = () => {
    approve.mutate(
      {
        ...(wholeItem && locationId ? { stockLocationId: locationId } : {}),
        ...(comment.trim() ? { reviewComment: comment.trim() } : {}),
      },
      { onSuccess: () => onOpenChange(false) },
    )
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{t('stock.adjustments.approve')}</DialogTitle>
          <DialogDescription>{t('stock.adjustments.approveHint')}</DialogDescription>
        </DialogHeader>

        {wholeItem ? (
          <div className="flex flex-col gap-2">
            <Label htmlFor="adjustment-location">{t('stock.adjustments.pickLocation')}</Label>
            <Select value={locationId} onValueChange={setLocationId}>
              <SelectTrigger
                id="adjustment-location"
                aria-label={t('stock.adjustments.pickLocation')}
              >
                <SelectValue placeholder={t('stock.adjustments.pickLocationPlaceholder')} />
              </SelectTrigger>
              <SelectContent>
                {request.corrections.map((entry) => (
                  <SelectItem key={entry.location.id} value={entry.location.id}>
                    {formatAdjustmentLocation(entry.location)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <p className="text-xs text-muted-foreground">
              {t('stock.adjustments.pickLocationHint')}
            </p>
          </div>
        ) : null}

        {correction ? (
          <CorrectionRecap correction={correction} movementType={request.movementType} />
        ) : null}

        <div className="flex flex-col gap-2">
          <Label htmlFor="adjustment-approve-comment">
            {t('stock.adjustments.answerOptional')}
          </Label>
          <Textarea
            id="adjustment-approve-comment"
            value={comment}
            maxLength={2000}
            onChange={(event) => setComment(event.target.value)}
          />
        </div>

        <DecisionError error={approve.error} />

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            type="button"
            onClick={submit}
            disabled={approve.isPending || correction === undefined}
          >
            {t('stock.adjustments.approve')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
