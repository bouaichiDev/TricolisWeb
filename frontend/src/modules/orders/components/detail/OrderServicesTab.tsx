import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { Button } from '@/shared/components/ui/button'

import { useDeleteOrderService } from '../../hooks/useOrderContent'
import type { OrderPackage, OrderService } from '../../types/orderDetail'
import { ChangeServiceStatusDialog } from './ChangeServiceStatusDialog'
import { OrderServiceCardView } from './OrderServiceCardView'
import { OrderServiceDialog } from './OrderServiceDialog'
import { OrderServicePanel } from './OrderServicePanel'
import { StatusTimelineSheet } from './StatusTimelineSheet'

interface OrderServicesTabProps {
  orderId: string
  customerId: string
  services: OrderService[]
  packages: OrderPackage[]
  editable: boolean
}

/**
 * Services de la commande, en grille de vignettes.
 *
 * Chaque service porte son adresse, son créneau, ses contacts et ses colis :
 * il n'y a pas d'onglet « Arrêts », parce qu'il n'y a pas d'entité `OrderStop`.
 *
 * La vignette montre de quoi reconnaître le service ; le panneau latéral porte
 * le détail et les actions. Empiler quatorze champs par service transformait
 * l'onglet en liste interminable dès trois prestations.
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

  const [openId, setOpenId] = useState<string | null>(null)
  const [editing, setEditing] = useState<OrderService | null>(null)
  const [creating, setCreating] = useState(false)
  const [deleting, setDeleting] = useState<OrderService | null>(null)
  const [changingStatus, setChangingStatus] = useState<OrderService | null>(null)
  const [history, setHistory] = useState<OrderService | null>(null)

  const ordered = [...services].sort((a, b) => a.sequence - b.sequence)
  const open = ordered.find((service) => service.id === openId) ?? null

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="font-semibold">{t('orders.services.title')}</p>
          <p className="text-sm text-muted-foreground">{t('orders.services.description')}</p>
        </div>

        {editable ? (
          <PermissionGuard permission="order_services.create">
            <Button type="button" variant="outline" size="sm" onClick={() => setCreating(true)}>
              <Plus className="size-4" aria-hidden />
              {t('orders.services.add')}
            </Button>
          </PermissionGuard>
        ) : null}
      </div>

      {ordered.length === 0 ? (
        <EmptyState title={t('orders.services.title')} />
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {ordered.map((service, index) => (
            <OrderServiceCardView
              key={service.id}
              service={service}
              position={index + 1}
              onOpen={() => setOpenId(service.id)}
              onHistory={() => setHistory(service)}
              onChangeStatus={() => setChangingStatus(service)}
            />
          ))}
        </div>
      )}

      <OrderServicePanel
        orderId={orderId}
        service={open}
        packages={packages}
        editable={editable}
        onClose={() => setOpenId(null)}
        onEdit={() => {
          setEditing(open)
          setOpenId(null)
        }}
        onDelete={() => {
          setDeleting(open)
          setOpenId(null)
        }}
        onChangeStatus={() => {
          setChangingStatus(open)
          setOpenId(null)
        }}
        onHistory={() => {
          setHistory(open)
          setOpenId(null)
        }}
      />

      {editing !== null || creating ? (
        <OrderServiceDialog
          key={editing?.id ?? 'new'}
          orderId={orderId}
          customerId={customerId}
          service={editing}
          open
          onOpenChange={(next) => {
            if (!next) {
              setEditing(null)
              setCreating(false)
            }
          }}
        />
      ) : null}

      <ChangeServiceStatusDialog
        orderId={orderId}
        service={changingStatus}
        open={changingStatus !== null}
        onOpenChange={(next) => !next && setChangingStatus(null)}
      />

      <StatusTimelineSheet
        entityType="order_service"
        entityId={history?.id ?? null}
        title={history?.service?.name ?? history?.serviceNumber}
        subtitle={history?.serviceNumber}
        currentStatus={history?.status}
        onClose={() => setHistory(null)}
      />

      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(next) => !next && setDeleting(null)}
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
