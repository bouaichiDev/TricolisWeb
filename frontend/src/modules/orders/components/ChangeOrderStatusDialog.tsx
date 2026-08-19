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
import { ORDER_MANUALLY_ASSIGNABLE, ORDER_STATUSES } from '../types/order'

interface ChangeOrderStatusDialogProps {
  orderId: string
  /** Transitions calculées par le backend ; l'écran n'en déduit aucune autre. */
  allowedTransitions: string[]
  /** Statut actuel, pour le distinguer des statuts simplement hors d'atteinte. */
  currentStatus: string | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Changement de statut d'une commande.
 *
 * **Les dix statuts sont montrés**, pour que le cycle de vie complet reste
 * lisible. Seuls ceux d'`allowedTransitions` sont sélectionnables : la machine
 * à états vit dans le backend, et proposer une transition qu'il refuserait ne
 * ferait que produire un 409.
 *
 * Deux raisons distinctes rendent un statut indisponible, et l'écran les
 * distingue :
 *
 * - **la transition n'existe pas** depuis le statut actuel — on ne passe pas de
 *   « Brouillon » à « Terminée » sans passer par les étapes intermédiaires ;
 * - **le statut ne se pose pas à la main** : planification et facturation sont
 *   produites par leurs modules, les déclarer ici laisserait croire qu'une
 *   commande est planifiée sans tournée.
 *
 * Un 409 signifie que l'état a changé entre l'affichage et l'envoi. Son message
 * est rédigé pour être lu tel quel, il n'est pas réécrit.
 */
export function ChangeOrderStatusDialog({
  orderId,
  allowedTransitions,
  currentStatus,
  open,
  onOpenChange,
}: ChangeOrderStatusDialogProps) {
  const { t } = useTranslation()
  const [status, setStatus] = useState('')
  const [error, setError] = useState<string | null>(null)
  const changeStatus = useChangeOrderStatus(orderId)

  const options = ORDER_STATUSES.map((value) => {
    const allowed = allowedTransitions.includes(value)

    return {
      value,
      label: t(`orderStatuses.${value}`),
      disabled: !allowed,
      hint: allowed
        ? undefined
        : value === currentStatus
          ? t('orders.statusDialog.current')
          : (ORDER_MANUALLY_ASSIGNABLE as readonly string[]).includes(value)
            ? t('orders.statusDialog.unreachable')
            : t('orders.statusDialog.systemManaged'),
    }
  })

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
        ) : null}

        <AsyncSelect
          label={t('orders.statusDialog.newStatus')}
          value={status}
          onChange={setStatus}
          options={options}
          required
          description={t('orders.statusDialog.hint')}
        />

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
