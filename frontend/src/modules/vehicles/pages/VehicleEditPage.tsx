import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { PageHeader } from '@/shared/components/layout/PageHeader'

import { VehicleForm } from '../components/VehicleForm'
import { useUpdateVehicle, useVehicle } from '../hooks/useVehicles'

export function VehicleEditPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams()

  const vehicle = useVehicle(id)
  const update = useUpdateVehicle()

  if (vehicle.error) {
    return <ErrorState error={vehicle.error} onRetry={() => void vehicle.refetch()} />
  }

  if (vehicle.data === undefined) {
    return <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('vehicles.editTitle')} description={vehicle.data.code} />

      <VehicleForm
        vehicle={vehicle.data}
        isPending={update.isPending}
        onCancel={() => void navigate(`/vehicles/${id}`)}
        onSubmit={async (payload) => {
          await update.mutateAsync({ id, ...payload })
          void navigate(`/vehicles/${id}`)
        }}
      />
    </div>
  )
}
