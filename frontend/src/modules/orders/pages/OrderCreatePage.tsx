import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'

import { PageHeader } from '@/shared/components/layout/PageHeader'

import { OrderFormShell } from '../components/wizard/OrderFormShell'
import { useOrderDraft } from '../hooks/useOrderDraft'
import { useCreateOrder } from '../hooks/useOrders'
import { mapOrderErrors, type OrderErrorReport } from '../schemas/orderErrors'
import { serializeOrderWithKeys } from '../schemas/serializeOrder'
import { validateDraft } from '../schemas/validateDraft'

const EMPTY_REPORT: OrderErrorReport = { issues: [], stepsInError: [], message: null }

/**
 * Création d'une commande.
 *
 * Deux sources d'erreurs, un seul format : le contrôle local reprend les règles
 * de `StoreOrderRequest`, et le 422 du serveur est traduit dans le même
 * vocabulaire. L'écran ne fait donc pas de différence entre les deux, et la
 * saisie survit dans les deux cas — rien n'est réinitialisé après un refus.
 */
export function OrderCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const controller = useOrderDraft()
  const createOrder = useCreateOrder()
  const [report, setReport] = useState<OrderErrorReport>(EMPTY_REPORT)

  const onSubmit = () => {
    const local = validateDraft(controller.draft)

    if (local.issues.length > 0) {
      setReport(local)
      return
    }

    const serialized = serializeOrderWithKeys(controller.draft)
    setReport(EMPTY_REPORT)

    createOrder.mutate(serialized.payload, {
      onSuccess: (order) => navigate(`/orders/${order.id}`),
      onError: (error) => setReport(mapOrderErrors(error, serialized)),
    })
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('orders.create')} description={t('orders.subtitle')} />

      <OrderFormShell
        controller={controller}
        report={report}
        isSubmitting={createOrder.isPending}
        onSubmit={onSubmit}
        onCancel={() => navigate('/orders')}
      />
    </div>
  )
}
