import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'

import { PageHeader } from '@/shared/components/layout/PageHeader'

import { StockLocationForm } from '../components/StockLocationForm'
import { useCreateStockLocation } from '../hooks/useStockLocations'
import {
  toStockLocationPayload,
  type StockLocationFormValues,
} from '../schemas/stockLocationSchema'

export function StockLocationCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateStockLocation()

  const submit = async (values: StockLocationFormValues) => {
    const location = await create.mutateAsync(toStockLocationPayload(values))
    await navigate(`/stock/locations/${location.id}`)
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('stock.newLocation')} description={t('stock.newLocationHint')} />

      <StockLocationForm
        onSubmit={submit}
        onCancel={() => void navigate('/stock/locations')}
        submitLabel={t('common.create')}
      />
    </div>
  )
}
