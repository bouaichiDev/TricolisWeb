import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'

import { StockLocationForm } from '../components/StockLocationForm'
import { useStockLocation, useUpdateStockLocation } from '../hooks/useStockLocations'
import {
  toStockLocationFormValues,
  toStockLocationUpdatePayload,
  type StockLocationFormValues,
} from '../schemas/stockLocationSchema'

export function StockLocationEditPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams<{ id: string }>()

  const { data: location, isPending, error, refetch } = useStockLocation(id)
  const update = useUpdateStockLocation()

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!location) return null

  const submit = async (values: StockLocationFormValues) => {
    await update.mutateAsync({ id, ...toStockLocationUpdatePayload(values) })
    await navigate(`/stock/locations/${id}`)
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('stock.editLocation')} description={location.locationCode} />

      <StockLocationForm
        defaultValues={toStockLocationFormValues(location)}
        onSubmit={submit}
        onCancel={() => void navigate(`/stock/locations/${id}`)}
        submitLabel={t('common.save')}
        lockDepot
        currentId={id}
      />
    </div>
  )
}
