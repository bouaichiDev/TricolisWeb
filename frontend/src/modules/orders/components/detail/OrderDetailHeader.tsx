import { Copy, Pencil, RefreshCw, Trash2, Truck } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import { formatDate, formatDateTime } from '@/shared/utils/format'

import { deliveryWindow } from '../../schemas/orderTotals'
import type { OrderDetail } from '../../types/orderDetail'
import { OrderSourceBadge, OrderStatusBadge } from '../OrderStatusBadge'

interface OrderDetailHeaderProps {
  order: OrderDetail
  onChangeStatus: () => void
  onDuplicate: () => void
  onDelete: () => void
}

/**
 * En-tête de la fiche commande.
 *
 * La **date de livraison** y tient la première place — c'est ce qu'on cherche
 * en ouvrant une commande. Elle n'existe pas en base : `orders.order_date` est
 * la date de la commande. Elle est dérivée des dates demandées des services,
 * qui portent la planification, et présentée comme un créneau lorsque la
 * commande s'étale sur plusieurs jours.
 */
export function OrderDetailHeader({
  order,
  onChangeStatus,
  onDuplicate,
  onDelete,
}: OrderDetailHeaderProps) {
  const { t } = useTranslation()
  const window = deliveryWindow(order.services ?? [])
  const spansDays = window.from !== null && window.to !== null && window.from !== window.to

  const subtitle = [
    order.customer?.name,
    order.agency?.name,
    order.depot?.name,
    t('orders.createdOn', { date: formatDateTime(order.createdAt) }),
  ].filter((part): part is string => Boolean(part))

  return (
    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div className="min-w-0">
        <div className="flex flex-wrap items-center gap-2.5">
          <h1 className="text-2xl font-semibold tracking-tight">{order.orderNumber}</h1>
          <OrderStatusBadge status={order.status} label={order.statusLabel} />
          <OrderSourceBadge source={order.source} />
        </div>

        <div className="mt-2 flex flex-wrap items-center gap-2">
          <Truck className="size-4 text-primary" aria-hidden />
          <span className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
            {t('orders.deliveryDate')}
          </span>
          <span className="font-mono text-lg font-semibold">
            {window.from === null ? '—' : formatDate(window.from)}
          </span>
          {spansDays ? (
            <Badge variant="outline" className="font-normal">
              {t('orders.deliveryWindow', {
                from: formatDate(window.from),
                to: formatDate(window.to),
              })}
            </Badge>
          ) : null}
        </div>

        <p className="mt-2 flex flex-wrap gap-x-1.5 text-sm text-muted-foreground">
          {subtitle.map((part, index) => (
            <span key={part}>
              {index > 0 ? <span className="mr-1.5 opacity-50">·</span> : null}
              {part}
            </span>
          ))}
        </p>
      </div>

      <div className="flex shrink-0 flex-wrap gap-2">
        {order.allowsContentChanges ? (
          <PermissionGuard permission="orders.delete">
            <Button variant="ghost" onClick={onDelete}>
              <Trash2 className="size-4" aria-hidden />
              {t('common.delete')}
            </Button>
          </PermissionGuard>
        ) : null}

        <PermissionGuard permission="orders.duplicate">
          <Button variant="outline" onClick={onDuplicate}>
            <Copy className="size-4" aria-hidden />
            {t('orders.duplicate.title')}
          </Button>
        </PermissionGuard>

        <PermissionGuard permission="orders.change_status">
          <Button variant="outline" onClick={onChangeStatus}>
            <RefreshCw className="size-4" aria-hidden />
            {t('orders.statusDialog.title')}
          </Button>
        </PermissionGuard>

        {order.allowsContentChanges ? (
          <PermissionGuard permission="orders.update">
            <Button asChild>
              <Link to={`/orders/${order.id}/edit`}>
                <Pencil className="size-4" aria-hidden />
                {t('common.edit')}
              </Link>
            </Button>
          </PermissionGuard>
        ) : null}
      </div>
    </div>
  )
}
