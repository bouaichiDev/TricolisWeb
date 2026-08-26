import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { PageHeader } from '@/shared/components/layout/PageHeader'

import { TourForm } from '../components/TourForm'
import { useTour, useUpdateTour } from '../hooks/useTours'

export function TourEditPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams()

  const tour = useTour(id)
  const update = useUpdateTour(id)

  if (tour.error) {
    return <ErrorState error={tour.error} onRetry={() => void tour.refetch()} />
  }

  if (tour.data === undefined) {
    return <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('tours.editTitle')} description={tour.data.tourNumber} />

      <TourForm
        tour={tour.data}
        isPending={update.isPending}
        onCancel={() => void navigate(`/tours/${id}`)}
        onSubmit={async (payload) => {
          await update.mutateAsync(payload)
          void navigate(`/tours/${id}`)
        }}
      />
    </div>
  )
}
