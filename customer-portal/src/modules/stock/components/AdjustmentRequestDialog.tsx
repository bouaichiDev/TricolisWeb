import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'

import { ApiError, errorMessage } from '@/shared/api/errors'
import { Field } from '@/shared/components/form/Field'
import { SelectField } from '@/shared/components/form/SelectField'
import { applyServerErrors } from '@/shared/components/form/serverErrors'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog'
import { Label } from '@/shared/components/ui/label'
import { Textarea } from '@/shared/components/ui/textarea'

import { adjustmentsApi } from '../api/adjustmentsApi'
import { useStockBalances, useStockMutation } from '../hooks/useStock'
import type { StockItem } from '../types'
import { formatQuantity } from '../utils/quantity'
import { locationLabel } from './stockColumns'

const schema = z.object({
  stockLocationId: z.string(),
  requestedQuantity: z.string().trim().regex(/^\d+([.,]\d{1,3})?$/, 'Une quantité positive, trois décimales au plus.'),
  reason: z.string().trim().min(1, 'Indiquez le motif de la demande.').max(255, '255 caractères au plus.'),
  comment: z.string().trim().max(2000, '2 000 caractères au plus.'),
})

type Values = z.infer<typeof schema>

/**
 * Demander une correction de quantité (§19). La demande part au
 * transporteur ; **le stock affiché ne change pas** tant qu'il ne l'a pas
 * acceptée et enregistré le mouvement.
 */
export function AdjustmentRequestDialog({ item, open, onOpenChange }: { item: StockItem; open: boolean; onOpenChange: (open: boolean) => void }) {
  const [formError, setFormError] = useState<string | null>(null)
  const balances = useStockBalances(item.id)
  const create = useStockMutation(adjustmentsApi.create)
  const form = useForm<Values>({ resolver: zodResolver(schema), defaultValues: { stockLocationId: '', requestedQuantity: '', reason: '', comment: '' } })
  const locationId = useWatch({ control: form.control, name: 'stockLocationId' })
  const displayed = locationId ? balances.data?.find((balance) => balance.location?.id === locationId)?.quantity : item.stock.quantity

  const submit = form.handleSubmit(async (values) => {
    setFormError(null)

    try {
      await create.mutateAsync({
        stockItemId: item.id,
        stockLocationId: values.stockLocationId || null,
        requestedQuantity: values.requestedQuantity.replace(',', '.'),
        reason: values.reason,
        comment: values.comment || null,
      })
      toast.success('Demande envoyée à votre transporteur. Le stock reste inchangé jusqu’à sa décision.')
      form.reset()
      onOpenChange(false)
    } catch (caught) {
      applyServerErrors(form, caught, ['stockLocationId', 'requestedQuantity', 'reason', 'comment'])
      if (!(caught instanceof ApiError && caught.isValidation)) setFormError(errorMessage(caught))
    }
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Demander une correction — {item.articleCode}</DialogTitle>
          <DialogDescription>Signalez l’écart constaté : votre transporteur vérifie, puis accepte ou refuse la correction.</DialogDescription>
        </DialogHeader>
        <form onSubmit={(event) => void submit(event)} className="grid gap-4" noValidate>
          {formError && (
            <Alert variant="destructive">
              <AlertDescription>{formError}</AlertDescription>
            </Alert>
          )}
          <SelectField
            form={form}
            name="stockLocationId"
            label="Emplacement"
            placeholder="Tout l’article"
            options={(balances.data ?? []).map((balance) => ({ value: balance.location?.id ?? '', label: locationLabel(balance.location) }))}
          />
          <p className="text-muted-foreground text-sm" data-testid="displayed-quantity">
            Quantité affichée : <span className="text-foreground font-medium tabular-nums">{formatQuantity(displayed)}</span>
          </p>
          <Field form={form} name="requestedQuantity" label="Quantité constatée" inputMode="decimal" />
          <Field form={form} name="reason" label="Motif" maxLength={255} />
          <div className="grid gap-1.5">
            <Label htmlFor="adjustment-comment">Commentaire</Label>
            <Textarea id="adjustment-comment" rows={3} maxLength={2000} {...form.register('comment')} />
          </div>
          <DialogFooter>
            <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
              Annuler
            </Button>
            <Button type="submit" disabled={create.isPending}>
              Envoyer la demande
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
