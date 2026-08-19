import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'

import { useDeleteOrderService } from '../../hooks/useOrderContent'
import type { OrderPackage, OrderService } from '../../types/orderDetail'
import { ChangeServiceStatusDialog } from './ChangeServiceStatusDialog'
import { OrderServiceCardView } from './OrderServiceCardView'
import { OrderServiceDialog } from './OrderServiceDialog'

interface OrderServicesTabProps {
  orderId: string
  customerId: string
  services: OrderService[]
  packages: OrderPackage[]
  editable: boolean
}

/**
 * Services de la commande, dans l'ordre de passage.
 *
 * Chaque service porte son adresse, son créneau, ses contacts et ses colis :
 * il n'y a pas d'onglet « Arrêts », parce qu'il n'y a pas d'entité `OrderStop`.
 */
export function OrderServicesTab({
  orderId,
  customerId,
  services,
  packages,
  editable,
}: OrderServicesTabProps) {
  const { t } = useTranslation()
  const remove = useDeleteOrderService(orderId)

  const [editing, setEditing] = useState<OrderService | null>(null)
  const [creating, setCreating] = useState(false)
  const [deleting, setDeleting] = useState<OrderService | null>(null)
  const [changingStatus, setChangingStatus] = useState<OrderService | null>(null)

  const packageLabel = new Map(
    packages.map((item) => [item.id, item.reference ?? item.barcode ?? item.id]),
  )

  const ordered = [...services].sort((a, b) => a.sequence - b.sequence)

  const addAction = editable ? (
    <PermissionGuard permission="order_services.create">
      <Button type="button" variant="outline" size="sm" onClick={() => setCreating(true)}>
        <Plus className="size-4" aria-hidden />
        {t('orders.services.add')}
      </Button>
    </PermissionGuard>
  ) : null

  return (
    <div className="flex flex-col gap-6">
      <SectionCard title={t('orders.services.title')} actions={addAction}>
        {ordered.length === 0 ? (
          <EmptyState title={t('orders.services.title')} />
        ) : (
          <p className="text-sm text-muted-foreground">{t('orders.services.description')}</p>
        )}
      </SectionCard>

      {ordered.map((service) => (
        <OrderServiceCardView
          key={service.id}
          service={service}
          packageLabel={packageLabel}
          editable={editable}
          onEdit={() => setEditing(service)}
          onDelete={() => setDeleting(service)}
          onChangeStatus={() => setChangingStatus(service)}
        />
      ))}

      <OrderServiceDialog
        key={editing?.id ?? 'new'}
        orderId={orderId}
        customerId={customerId}
        service={editing}
        open={editing !== null || creating}
        onOpenChange={(open) => {
          if (!open) {
            setEditing(null)
            setCreating(false)
          }
        }}
      />

      <ChangeServiceStatusDialog
        orderId={orderId}
        service={changingStatus}
        open={changingStatus !== null}
        onOpenChange={(open) => !open && setChangingStatus(null)}
      />

      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={t('orders.services.remove')}
        description={t('orders.services.deleteConfirm')}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          if (deleting === null) return
          remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })
        }}
      />
    </div>
  )
}
