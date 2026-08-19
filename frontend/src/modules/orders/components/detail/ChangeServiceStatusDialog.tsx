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

import { useChangeOrderServiceStatus } from '../../hooks/useOrderContent'
import { ORDER_SERVICE_STATUSES } from '../../types/order'
import type { OrderService } from '../../types/orderDetail'

interface ChangeServiceStatusDialogProps {
  orderId: string
  service: OrderService | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Statut d'un service de commande.
 *
 * **Les neuf statuts sont proposés**, et c'est exact : contrairement à la
 * commande, `UpdateOrderServiceStatusRequest` valide seulement l'appartenance à
 * l'énumération — il n'existe pas de machine à états pour un service. En
 * restreindre la liste ici inventerait une règle que le serveur n'applique pas.
 */
export function ChangeServiceStatusDialog({
  orderId,
  service,
  open,
  onOpenChange,
}: ChangeServiceStatusDialogProps) {
  const { t } = useTranslation()
  const [status, setStatus] = useState('')
  const [error, setError] = useState<string | null>(null)
  const changeStatus = useChangeOrderServiceStatus(orderId)

  const close = () => {
    setStatus('')
    setError(null)
    onOpenChange(false)
  }

  const submit = () => {
    if (service === null || status === '') return

    setError(null)
    changeStatus.mutate(
      { serviceId: service.id, status },
      {
        onSuccess: close,
        onError: (cause) => {
          setError(cause instanceof ApiError ? cause.message : t('errors.unexpected'))
        },
      },
    )
  }

  return (
    <Dialog open={open} onOpenChange={(next) => (next ? onOpenChange(true) : close())}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('orders.services.changeStatus')}</DialogTitle>
          <DialogDescription>
            {service?.service?.name ?? service?.serviceNumber ?? ''}
          </DialogDescription>
        </DialogHeader>

        {error !== null ? (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        ) : null}

        <AsyncSelect
          label={t('orders.statusDialog.newStatus')}
          value={status === '' ? (service?.status ?? '') : status}
          onChange={setStatus}
          options={ORDER_SERVICE_STATUSES.map((value) => ({
            value,
            label: t(`orderServiceStatuses.${value}`),
          }))}
          required
        />

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={close}>
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
