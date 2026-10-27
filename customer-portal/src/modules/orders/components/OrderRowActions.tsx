import { Ban, Copy, Eye, MapPin, Pencil } from 'lucide-react'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'

import { paths } from '@/app/router/paths'
import { errorMessage } from '@/shared/api/errors'
import { RowActions, type RowAction } from '@/shared/components/data/RowActions'

import { ordersApi } from '../api/ordersApi'
import { useOrderMutation } from '../hooks/useOrders'
import type { OrderListItem } from '../types'
import { CancelOrderDialog } from './CancelOrderDialog'

/**
 * Les actions d'une commande dans la liste, en icônes comme au back-office :
 * mêmes gestes que l'en-tête de la fiche (`OrderHeaderActions`), chacun selon
 * `order.actions`, calculé par le backend — aucune règle de statut ici.
 */
export function OrderRowActions({ order }: { order: OrderListItem }) {
  const navigate = useNavigate()
  const [cancelling, setCancelling] = useState(false)
  const duplicate = useOrderMutation(ordersApi.duplicate)

  const duplicateOrder = async () => {
    if (!window.confirm(`Dupliquer la commande ${order.reference} ? Une nouvelle commande sera créée.`)) return

    try {
      const copy = await duplicate.mutateAsync(order.id)
      toast.success(`Commande ${copy.reference} créée par duplication.`)
      navigate(paths.order(copy.id))
    } catch (caught) {
      toast.error(errorMessage(caught))
    }
  }

  const actions: RowAction[] = [
    { key: 'view', icon: Eye, label: 'Consulter', to: paths.order(order.id) },
    ...(order.actions.canTrack ? [{ key: 'track', icon: MapPin, label: 'Suivre', to: paths.orderTracking(order.id) }] : []),
    { key: 'edit', icon: Pencil, label: 'Modifier', ...(order.actions.canEdit ? { to: paths.orderEdit(order.id) } : { disabled: true }) },
    ...(order.actions.canDuplicate ? [{ key: 'duplicate', icon: Copy, label: 'Dupliquer', disabled: duplicate.isPending, onClick: () => void duplicateOrder() }] : []),
    ...(order.actions.canCancel ? [{ key: 'cancel', icon: Ban, label: 'Annuler la commande', tone: 'danger' as const, onClick: () => setCancelling(true) }] : []),
  ]

  return (
    <>
      <RowActions actions={actions} />
      {cancelling && <CancelOrderDialog orderId={order.id} reference={order.reference} open onOpenChange={setCancelling} />}
    </>
  )
}
