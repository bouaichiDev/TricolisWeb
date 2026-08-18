import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'

import { ApiError } from '@/shared/api/errors'
import { ControlledCheckbox } from '@/shared/components/form/ControlledCheckbox'
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

import { useDuplicateOrder } from '../hooks/useOrders'
import type { DuplicateOrderPayload } from '../types/orderPayload'

/**
 * Les cinq options réellement acceptées par `DuplicateOrderRequest`.
 *
 * Aucune n'est inventée, et aucune n'est omise. Les colis dépendent des lignes
 * dans la commande d'origine, mais le backend ne l'impose pas : l'écran ne
 * bride donc pas des combinaisons qu'il accepte.
 */
const OPTIONS = ['lines', 'packages', 'services', 'contacts', 'documents'] as const

interface DuplicateOrderDialogProps {
  orderId: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function DuplicateOrderDialog({
  orderId,
  open,
  onOpenChange,
}: DuplicateOrderDialogProps) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [selection, setSelection] = useState<DuplicateOrderPayload>({
    lines: true,
    packages: true,
    services: true,
    contacts: true,
    documents: false,
  })
  const [error, setError] = useState<string | null>(null)
  const duplicate = useDuplicateOrder(orderId)

  const submit = () => {
    setError(null)
    duplicate.mutate(selection, {
      onSuccess: (order) => {
        onOpenChange(false)
        navigate(`/orders/${order.id}`)
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
          <DialogTitle>{t('orders.duplicate.title')}</DialogTitle>
          <DialogDescription>{t('orders.duplicate.description')}</DialogDescription>
        </DialogHeader>

        {error !== null ? (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        ) : null}

        <div className="flex flex-col gap-1">
          {OPTIONS.map((option) => (
            <ControlledCheckbox
              key={option}
              label={t(`orders.duplicate.${option}`)}
              checked={selection[option] === true}
              onChange={(checked) =>
                setSelection((current) => ({ ...current, [option]: checked }))
              }
            />
          ))}
        </div>

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={submit} disabled={duplicate.isPending}>
            {t('orders.duplicate.submit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
