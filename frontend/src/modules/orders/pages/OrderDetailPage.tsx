import { Copy, RefreshCw } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/components/ui/tabs'

import { ChangeOrderStatusDialog } from '../components/ChangeOrderStatusDialog'
import { DuplicateOrderDialog } from '../components/DuplicateOrderDialog'
import { OrderDocumentsTab } from '../components/detail/OrderDocumentsTab'
import { OrderHistoryTimeline } from '../components/detail/OrderHistoryTimeline'
import { OrderLinesTab } from '../components/detail/OrderLinesTab'
import { OrderPackagesTab } from '../components/detail/OrderPackagesTab'
import { OrderServicesTab } from '../components/detail/OrderServicesTab'
import { OrderSummaryTab } from '../components/detail/OrderSummaryTab'
import { useDeleteOrder, useOrder } from '../hooks/useOrders'

/**
 * Fiche commande à onglets.
 *
 * Six onglets, pas sept : il n'y a pas d'onglet « Arrêts », parce que le modèle
 * ne comporte pas d'entité `OrderStop`. L'adresse et le créneau sont portés par
 * chaque service, et figurent donc dans l'onglet Services.
 *
 * Tout ce qui est affiché ici vient d'un seul appel, `GET /orders/{order}`,
 * sauf l'arbre des colis, l'historique et les documents, qui ont leurs propres
 * routes.
 */
export function OrderDetailPage() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const { data: order, isPending, error, refetch } = useOrder(id)
  const deleteOrder = useDeleteOrder()

  const [statusOpen, setStatusOpen] = useState(false)
  const [duplicateOpen, setDuplicateOpen] = useState(false)
  const [confirmDelete, setConfirmDelete] = useState(false)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!order) return null

  return (
    <div className="flex flex-col gap-6">
      <EntityHeader
        title={order.orderNumber}
        subtitle={order.customer?.name}
        editTo={order.allowsContentChanges ? `/orders/${order.id}/edit` : undefined}
        editPermission={order.allowsContentChanges ? 'orders.update' : undefined}
        onDelete={order.allowsContentChanges ? () => setConfirmDelete(true) : undefined}
        deletePermission={order.allowsContentChanges ? 'orders.delete' : undefined}
        actions={
          <>
            <PermissionGuard permission="orders.change_status">
              <Button variant="outline" onClick={() => setStatusOpen(true)}>
                <RefreshCw className="size-4" aria-hidden />
                {t('orders.statusDialog.title')}
              </Button>
            </PermissionGuard>

            <PermissionGuard permission="orders.duplicate">
              <Button variant="outline" onClick={() => setDuplicateOpen(true)}>
                <Copy className="size-4" aria-hidden />
                {t('orders.duplicate.title')}
              </Button>
            </PermissionGuard>
          </>
        }
      />

      {!order.allowsContentChanges ? (
        <Alert>
          <AlertDescription>{t('orders.locked')}</AlertDescription>
        </Alert>
      ) : null}

      <Tabs defaultValue="summary">
        <TabsList className="w-full justify-start overflow-x-auto">
          <TabsTrigger value="summary">{t('orders.tabs.summary')}</TabsTrigger>
          <TabsTrigger value="lines">{t('orders.tabs.lines')}</TabsTrigger>
          <TabsTrigger value="packages">{t('orders.tabs.packages')}</TabsTrigger>
          <TabsTrigger value="services">{t('orders.tabs.services')}</TabsTrigger>
          <TabsTrigger value="documents">{t('orders.tabs.documents')}</TabsTrigger>
          <TabsTrigger value="history">{t('orders.tabs.history')}</TabsTrigger>
        </TabsList>

        <TabsContent value="summary" className="mt-6">
          <OrderSummaryTab order={order} />
        </TabsContent>

        <TabsContent value="lines" className="mt-6">
          <OrderLinesTab lines={order.lines ?? []} />
        </TabsContent>

        <TabsContent value="packages" className="mt-6">
          <OrderPackagesTab
            orderId={order.id}
            packages={order.packages ?? []}
            lines={order.lines ?? []}
          />
        </TabsContent>

        <TabsContent value="services" className="mt-6">
          <OrderServicesTab services={order.services ?? []} packages={order.packages ?? []} />
        </TabsContent>

        <TabsContent value="documents" className="mt-6">
          <OrderDocumentsTab orderId={order.id} />
        </TabsContent>

        <TabsContent value="history" className="mt-6">
          <OrderHistoryTimeline orderId={order.id} />
        </TabsContent>
      </Tabs>

      <ChangeOrderStatusDialog
        orderId={order.id}
        allowedTransitions={order.allowedTransitions}
        open={statusOpen}
        onOpenChange={setStatusOpen}
      />

      <DuplicateOrderDialog
        orderId={order.id}
        open={duplicateOpen}
        onOpenChange={setDuplicateOpen}
      />

      <ConfirmDialog
        open={confirmDelete}
        onOpenChange={setConfirmDelete}
        title={t('common.delete')}
        description={t('orders.deleteConfirm')}
        onConfirm={() => deleteOrder.mutate(order.id, { onSuccess: () => navigate('/orders') })}
      />
    </div>
  )
}
