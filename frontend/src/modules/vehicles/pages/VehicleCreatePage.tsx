import { useTranslation } from 'react-i18next'
import { useNavigate, useSearchParams } from 'react-router-dom'

import { PageHeader } from '@/shared/components/layout/PageHeader'

import { VehicleForm } from '../components/VehicleForm'
import { useCreateVehicle } from '../hooks/useVehicles'

export function VehicleCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const create = useCreateVehicle()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('vehicles.createTitle')} description={t('vehicles.subtitle')} />

      <VehicleForm
        providerId={params.get('providerId') ?? undefined}
        isPending={create.isPending}
        onCancel={() => void navigate('/vehicles')}
        onSubmit={async (payload) => {
          const vehicle = await create.mutateAsync(payload)
          void navigate(`/vehicles/${vehicle.id}`)
        }}
      />
    </div>
  )
}
