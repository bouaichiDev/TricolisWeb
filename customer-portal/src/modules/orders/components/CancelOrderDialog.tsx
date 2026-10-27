import { useId, useState } from 'react'
import { toast } from 'sonner'

import { ApiError, errorMessage } from '@/shared/api/errors'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog'
import { Label } from '@/shared/components/ui/label'
import { Textarea } from '@/shared/components/ui/textarea'

import { ordersApi } from '../api/ordersApi'
import { useOrderMutation } from '../hooks/useOrders'

interface Props {
  orderId: string
  reference: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** Annulation avec motif : la commande n'est jamais supprimée, elle change de statut. */
export function CancelOrderDialog({ orderId, reference, open, onOpenChange }: Props) {
  const reasonId = useId()
  const [reason, setReason] = useState('')
  const [error, setError] = useState<string | null>(null)
  const cancel = useOrderMutation((text: string) => ordersApi.cancel(orderId, text))

  const confirm = async () => {
    setError(null)

    try {
      await cancel.mutateAsync(reason)
      toast.success(`Commande ${reference} annulée.`)
      onOpenChange(false)
    } catch (caught) {
      const detail = caught instanceof ApiError ? (caught.fieldError('reasonText') ?? caught.fieldError('reason')) : undefined
      setError(detail ?? errorMessage(caught))
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Annuler la commande {reference}</DialogTitle>
          <DialogDescription>Votre transporteur sera informé de l’annulation.</DialogDescription>
        </DialogHeader>
        {error && (
          <Alert variant="destructive" role="alert">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}
        <div className="grid gap-1.5">
          <Label htmlFor={reasonId}>Motif</Label>
          <Textarea id={reasonId} rows={3} value={reason} onChange={(event) => setReason(event.target.value)} placeholder="Ex. Erreur dans la commande" />
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Retour
          </Button>
          <Button variant="destructive" disabled={cancel.isPending} onClick={() => void confirm()}>
            Confirmer l’annulation
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
