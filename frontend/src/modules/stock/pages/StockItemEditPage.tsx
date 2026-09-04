import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'

import { StockItemForm } from '../components/StockItemForm'
import { useStockItem, useUpdateStockItem } from '../hooks/useStockItems'
import {
  toStockItemFormValues,
  toStockItemUpdatePayload,
  type StockItemFormValues,
} from '../schemas/stockItemSchema'

export function StockItemEditPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams<{ id: string }>()

  const { data: item, isPending, error, refetch } = useStockItem(id)
  const update = useUpdateStockItem(id)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!item) return null

  const submit = async (values: StockItemFormValues) => {
    await update.mutateAsync(toStockItemUpdatePayload(values))
    await navigate(`/stock/items/${id}`)
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('stock.editItem')} description={item.articleCode} />

      <StockItemForm
        defaultValues={toStockItemFormValues(item)}
        onSubmit={submit}
        onCancel={() => void navigate(`/stock/items/${id}`)}
        submitLabel={t('common.save')}
        lockCustomer
      />
    </div>
  )
}
