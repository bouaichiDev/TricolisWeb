import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'

import { PageHeader } from '@/shared/components/layout/PageHeader'

import { StockReservationForm } from '../components/StockReservationForm'
import { useCreateStockReservation } from '../hooks/useStockReservations'
import {
  toStockReservationPayload,
  type StockReservationFormValues,
} from '../schemas/stockReservationSchema'

export function StockReservationCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateStockReservation()

  const submit = async (values: StockReservationFormValues) => {
    const reservation = await create.mutateAsync(toStockReservationPayload(values))
    await navigate(`/stock/reservations/${reservation.id}`)
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('stock.newReservation')}
        description={t('stock.newReservationHint')}
      />

      <StockReservationForm
        onSubmit={submit}
        onCancel={() => void navigate('/stock/reservations')}
        submitLabel={t('stock.reserve')}
      />
    </div>
  )
}
