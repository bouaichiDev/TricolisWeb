import { Ban, RotateCcw, Send, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/shared/components/ui/button'
import { usePermissions } from '@/shared/hooks/usePermission'

import {
  useCancelOrderCommunication,
  useDeleteOrderCommunication,
  useQueueOrderCommunication,
  useRetryOrderCommunication,
} from '../hooks/useOrderCommunications'
import { abilitiesOf } from '../utils/communicationActions'
import type { OrderCommunication } from '../types/communication'

interface CommunicationRowActionsProps {
  communication: OrderCommunication
}

/**
 * Actions d'une communication, gouvernées par son statut.
 *
 * Deux conditions, toutes deux nécessaires : le statut doit permettre l'action
 * — `abilitiesOf` recopie le graphe du serveur — et l'utilisateur doit avoir la
 * permission correspondante. `order_communications` en porte trois distinctes :
 * mettre en file, annuler et réessayer se gardent séparément.
 *
 * **« Mettre en file », pas « Envoyer ».** Il n'existe pas de route `send` : le
 * message entre en file, et le statut passe ensuite par `queued`, `sending`,
 * `sent`. Promettre un envoi immédiat serait faux.
 */
export function CommunicationRowActions({ communication }: CommunicationRowActionsProps) {
  const { t } = useTranslation()
  const { has } = usePermissions()

  const queue = useQueueOrderCommunication()
  const retry = useRetryOrderCommunication()
  const cancel = useCancelOrderCommunication()
  const remove = useDeleteOrderCommunication()

  const abilities = abilitiesOf(communication.status)
  const pending =
    queue.isPending || retry.isPending || cancel.isPending || remove.isPending

  const actions = [
    {
      key: 'queue',
      show: abilities.queue && has('order_communications.queue'),
      label: t('communications.queue'),
      icon: Send,
      run: () => queue.mutate(communication.id),
      destructive: false,
    },
    {
      key: 'retry',
      show: abilities.retry && has('order_communications.retry'),
      label: t('communications.retry'),
      icon: RotateCcw,
      run: () => retry.mutate(communication.id),
      destructive: false,
    },
    {
      key: 'cancel',
      show: abilities.cancel && has('order_communications.cancel'),
      label: t('communications.cancel'),
      icon: Ban,
      run: () => cancel.mutate(communication.id),
      destructive: false,
    },
    {
      key: 'delete',
      show: abilities.remove && has('order_communications.delete'),
      label: t('common.delete'),
      icon: Trash2,
      run: () => remove.mutate(communication.id),
      destructive: true,
    },
  ].filter((action) => action.show)

  if (actions.length === 0) return null

  return (
    <span className="flex justify-end gap-1">
      {actions.map((action) => (
        <Button
          key={action.key}
          type="button"
          variant="ghost"
          size="icon"
          disabled={pending}
          className={action.destructive ? 'text-destructive hover:text-destructive' : undefined}
          onClick={action.run}
          title={action.label}
          aria-label={action.label}
        >
          <action.icon className="size-4" aria-hidden />
        </Button>
      ))}
    </span>
  )
}
