import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'

import { PageHeader } from '@/shared/components/layout/PageHeader'

import { StockMovementForm } from '../components/StockMovementForm'
import { useCreateStockMovement } from '../hooks/useStockMovements'
import {
  toStockMovementPayload,
  type StockMovementFormValues,
} from '../schemas/stockMovementSchema'

export function StockMovementCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateStockMovement()

  const submit = async (values: StockMovementFormValues) => {
    const movement = await create.mutateAsync(toStockMovementPayload(values))
    await navigate(`/stock/movements/${movement.id}`)
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('stock.newMovement')} description={t('stock.newMovementHint')} />

      <StockMovementForm
        onSubmit={submit}
        onCancel={() => void navigate('/stock/movements')}
        submitLabel={t('stock.record')}
      />
    </div>
  )
}
