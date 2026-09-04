import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { useStatusOptions } from '@/modules/statuses/hooks/useStatuses'
import { ApiError } from '@/shared/api/errors'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { ControlledField } from '@/shared/components/form/ControlledField'
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

import { useChangeOrderStatus, useOrderStockPlan } from '../hooks/useOrders'
import { OrderStockPlanFields } from './OrderStockPlanFields'
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
 *
 * **Confirmer sort la marchandise du stock.** L'aperçu est donc chargé dès que
 * « Confirmée » est visé, et l'écran demande l'emplacement des seules lignes
 * dont l'article dort dans plusieurs endroits — le serveur trouve les autres
 * tout seul. Sans cela, la confirmation partirait pour revenir en 422.
 *
 * **Certains statuts exigent un motif**, et le champ n'apparaît que pour
 * ceux-là. Lesquels vient du **référentiel** — la colonne `requiresReason` de
 * `statuses` —, jamais d'une liste écrite ici : un administrateur peut en
 * exiger un sur d'autres statuts, et l'écran doit suivre. Sans ce champ,
 * annuler une commande était impossible : le serveur refusait, sans que rien à
 * l'écran permette de lui répondre.
 *
 * Le motif n'est pas conservé sur la commande : il part au journal d'audit,
 * avec la transition. C'est là qu'on cherche pourquoi une commande a été
 * annulée.
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
  const [reason, setReason] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [locations, setLocations] = useState<Record<string, string>>({})
  const changeStatus = useChangeOrderStatus(orderId)

  const confirming = status === 'confirmed'
  const plan = useOrderStockPlan(orderId, open && confirming)
  const planLines = plan.data ?? []

  // Le referentiel dit lesquels exigent un motif. Le deviner ici ferait mentir
  // l'ecran des qu'un administrateur en exigerait un ailleurs.
  //
  // Meme requete que celle des pastilles de statut : la fiche l'a deja chargee,
  // et ouvrir ce dialogue n'en declenche donc aucune de plus.
  const { statuses } = useStatusOptions('order')

  const reasonRequired =
    status !== '' && statuses.some((item) => item.code === status && item.requiresReason)

  const reasonMissing = reasonRequired && reason.trim() === ''

  const ambiguous = planLines.filter((line) => line.state === 'ambiguous')
  const blocked =
    reasonMissing ||
    (confirming &&
      (planLines.some((line) => line.state === 'insufficient') ||
        ambiguous.some((line) => (locations[line.orderLineId] ?? '') === '')))

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
    changeStatus.mutate(
      {
        status,
        stockLocations: Object.entries(locations).map(([orderLineId, stockLocationId]) => ({
          orderLineId,
          stockLocationId,
        })),
        reasonText: reason,
      },
      {
      onSuccess: () => {
        setStatus('')
        setReason('')
        setLocations({})
        onOpenChange(false)
      },
      onError: (cause) => {
        setError(cause instanceof ApiError ? cause.message : t('errors.unexpected'))
      },
      },
    )
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

        {reasonRequired ? (
          <ControlledField
            label={t('orders.statusDialog.reason')}
            value={reason}
            onChange={setReason}
            required
            multiline
            description={t('orders.statusDialog.reasonHint')}
          />
        ) : null}

        {confirming ? (
          <OrderStockPlanFields
            lines={planLines}
            isLoading={plan.isPending}
            choices={locations}
            onChange={(orderLineId, stockLocationId) =>
              setLocations((current) => ({ ...current, [orderLineId]: stockLocationId }))
            }
          />
        ) : null}

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            type="button"
            onClick={submit}
            disabled={status === '' || changeStatus.isPending || blocked}
          >
            {t('orders.statusDialog.submit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
