import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Badge } from '@/shared/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/components/ui/tabs'

import { ChangeOrderStatusDialog } from '../components/ChangeOrderStatusDialog'
import { DuplicateOrderDialog } from '../components/DuplicateOrderDialog'
import { OrderDetailHeader } from '../components/detail/OrderDetailHeader'
import { OrderDocumentsTab } from '../components/detail/OrderDocumentsTab'
import { OrderHistoryTimeline } from '../components/detail/OrderHistoryTimeline'
import { OrderKpiStrip } from '../components/detail/OrderKpiStrip'
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

  // Les compteurs viennent du contenu déjà chargé : aucun appel de plus.
  const tabs = [
    { value: 'summary', count: null },
    { value: 'lines', count: order.lines?.length ?? 0 },
    { value: 'packages', count: order.packages?.length ?? 0 },
    { value: 'services', count: order.services?.length ?? 0 },
    { value: 'documents', count: null },
    { value: 'history', count: null },
  ]

  return (
    <div className="flex flex-col gap-4">
      <OrderDetailHeader
        order={order}
        onChangeStatus={() => setStatusOpen(true)}
        onDuplicate={() => setDuplicateOpen(true)}
        onDelete={() => setConfirmDelete(true)}
      />

      <OrderKpiStrip order={order} />

      {!order.allowsContentChanges ? (
        <Alert>
          <AlertDescription>{t('orders.contentLocked')}</AlertDescription>
        </Alert>
      ) : null}

      <Tabs defaultValue="summary">
        <TabsList className="w-full justify-start overflow-x-auto">
          {tabs.map((tab) => (
            <TabsTrigger key={tab.value} value={tab.value} className="gap-1.5">
              {t(`orders.tabs.${tab.value}`)}
              {tab.count === null ? null : (
                <Badge variant="secondary" className="px-1.5 font-mono text-[11px]">
                  {tab.count}
                </Badge>
              )}
            </TabsTrigger>
          ))}
        </TabsList>

        <TabsContent value="summary" className="mt-4">
          <OrderSummaryTab order={order} />
        </TabsContent>

        <TabsContent value="lines" className="mt-4">
          <OrderLinesTab
            orderId={order.id}
            lines={order.lines ?? []}
            editable={order.allowsContentChanges}
          />
        </TabsContent>

        <TabsContent value="packages" className="mt-4">
          <OrderPackagesTab
            orderId={order.id}
            packages={order.packages ?? []}
            lines={order.lines ?? []}
            editable={order.allowsContentChanges}
          />
        </TabsContent>

        <TabsContent value="services" className="mt-4">
          <OrderServicesTab
            orderId={order.id}
            customerId={order.customerId}
            services={order.services ?? []}
            packages={order.packages ?? []}
            editable={order.allowsContentChanges}
          />
        </TabsContent>

        <TabsContent value="documents" className="mt-4">
          <OrderDocumentsTab orderId={order.id} />
        </TabsContent>

        <TabsContent value="history" className="mt-4">
          <OrderHistoryTimeline orderId={order.id} />
        </TabsContent>
      </Tabs>

      <ChangeOrderStatusDialog
        orderId={order.id}
        allowedTransitions={order.allowedTransitions}
        currentStatus={order.status}
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
        confirmLabel={t('common.delete')}
        onConfirm={() => deleteOrder.mutate(order.id, { onSuccess: () => navigate('/orders') })}
      />
    </div>
  )
}
