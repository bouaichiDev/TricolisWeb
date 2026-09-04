import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'

import { PageHeader } from '@/shared/components/layout/PageHeader'

import { TourForm } from '../components/TourForm'
import { useCreateTour } from '../hooks/useTours'

/**
 * Création d'une tournée.
 *
 * On arrive ici avant de planifier : il faut une tournée au brouillon pour y
 * verser des commandes. La fiche ouverte ensuite est celle où se changent les
 * états.
 */
export function TourCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateTour()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('tours.createTitle')} description={t('tours.createHint')} />

      <TourForm
        isPending={create.isPending}
        onCancel={() => void navigate('/tours')}
        onSubmit={async (payload) => {
          const tour = await create.mutateAsync(payload)
          void navigate(`/tours/${tour.id}`)
        }}
      />
    </div>
  )
}
