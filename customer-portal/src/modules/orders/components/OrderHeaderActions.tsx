import { Ban, Copy, MapPin, Pencil } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { toast } from 'sonner'

import { paths } from '@/app/router/paths'
import { errorMessage } from '@/shared/api/errors'
import { Button } from '@/shared/components/ui/button'

import { ordersApi } from '../api/ordersApi'
import { useOrderMutation } from '../hooks/useOrders'
import type { OrderDetail } from '../types'
import { CancelOrderDialog } from './CancelOrderDialog'

/**
 * Les boutons suivent `order.actions`, calculé par le backend : aucune règle
 * de statut n'est réécrite ici.
 */
export function OrderHeaderActions({ order }: { order: OrderDetail }) {
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

  return (
    <>
      {order.actions.canTrack && (
        <Button asChild variant="outline">
          <Link to={paths.orderTracking(order.id)}>
            <MapPin className="size-4" /> Suivre
          </Link>
        </Button>
      )}
      {order.actions.canEdit && (
        <Button asChild variant="outline">
          <Link to={paths.orderEdit(order.id)}>
            <Pencil className="size-4" /> Modifier
          </Link>
        </Button>
      )}
      {order.actions.canDuplicate && (
        <Button variant="outline" disabled={duplicate.isPending} onClick={() => void duplicateOrder()}>
          <Copy className="size-4" /> Dupliquer
        </Button>
      )}
      {order.actions.canCancel && (
        <Button variant="destructive" onClick={() => setCancelling(true)}>
          <Ban className="size-4" /> Annuler
        </Button>
      )}
      <CancelOrderDialog orderId={order.id} reference={order.reference} open={cancelling} onOpenChange={setCancelling} />
    </>
  )
}
