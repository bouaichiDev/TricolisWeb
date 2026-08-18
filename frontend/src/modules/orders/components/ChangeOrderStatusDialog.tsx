import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { ApiError } from '@/shared/api/errors'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'

import { useChangeOrderStatus } from '../hooks/useOrders'

interface ChangeOrderStatusDialogProps {
  orderId: string
  /** Transitions calculées par le backend ; l'écran n'en déduit aucune autre. */
  allowedTransitions: string[]
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Changement de statut d'une commande.
 *
 * Les statuts proposés sont exactement ceux de `allowedTransitions`, produits
 * par `OrderDetailResource` : la machine à états vit dans le backend, la
 * reproduire ici la ferait diverger au premier ajout de statut.
 *
 * Un 409 signifie que l'état a changé entre l'affichage et l'envoi. Son message
 * est rédigé pour être lu tel quel, il n'est pas réécrit.
 */
export function ChangeOrderStatusDialog({
  orderId,
  allowedTransitions,
  open,
  onOpenChange,
}: ChangeOrderStatusDialogProps) {
  const { t } = useTranslation()
  const [status, setStatus] = useState('')
  const [error, setError] = useState<string | null>(null)
  const changeStatus = useChangeOrderStatus(orderId)

  const submit = () => {
    if (status === '') return

    setError(null)
    changeStatus.mutate(status, {
      onSuccess: () => {
        setStatus('')
        onOpenChange(false)
      },
      onError: (cause) => {
        setError(cause instanceof ApiError ? cause.message : t('errors.unexpected'))
      },
    })
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('orders.statusDialog.title')}</DialogTitle>
          <DialogDescription>{t('orders.statusDialog.description')}</DialogDescription>
        </DialogHeader>

        {error !== null ? (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        ) : null}

        {allowedTransitions.length === 0 ? (
          <Alert>
            <AlertDescription>{t('orders.noTransition')}</AlertDescription>
          </Alert>
        ) : (
          <AsyncSelect
            label={t('orders.statusDialog.newStatus')}
            value={status}
            onChange={setStatus}
            options={allowedTransitions.map((value) => ({
              value,
              label: t(`orderStatuses.${value}`, { defaultValue: value }),
            }))}
            required
          />
        )}

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            type="button"
            onClick={submit}
            disabled={status === '' || changeStatus.isPending}
          >
            {t('orders.statusDialog.submit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
