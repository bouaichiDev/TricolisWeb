import { useTranslation } from 'react-i18next'
import type { UseFormReturn } from 'react-hook-form'

import { useDriverList } from '@/modules/drivers/hooks/useDrivers'
import { useProviderOptions } from '@/modules/providers/hooks/useProviders'
import { useVehicleList } from '@/modules/vehicles/hooks/useVehicles'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { SectionCard } from '@/shared/components/layout/SectionCard'

import { NONE, type TourFormValues } from './tourFormSchema'

/**
 * Les moyens affectés à la tournée : fournisseur, chauffeur, véhicule.
 *
 * Tous trois sont facultatifs, et c'est voulu : on compose souvent une tournée
 * la veille, avant de savoir qui la conduira. Le serveur ne les exige pas non
 * plus.
 *
 * Seuls les chauffeurs et véhicules **actifs** sont proposés : affecter un
 * véhicule en maintenance planifierait une tournée qui ne peut pas partir.
 */
export function TourResourceFields({ form }: { form: UseFormReturn<TourFormValues> }) {
  const { t } = useTranslation()

  const providers = useProviderOptions()
  const drivers = useDriverList({ page: 1, perPage: 100, status: 'active' })
  const vehicles = useVehicleList({ page: 1, perPage: 100, status: 'active' })

  const none = { value: NONE, label: t('tours.form.none') }

  return (
    <SectionCard title={t('tours.form.resources')} description={t('tours.form.resourcesHint')}>
      <div className="grid gap-4 sm:grid-cols-3">
        <AsyncSelect
          label={t('tours.fields.provider')}
          value={form.watch('providerId')}
          onChange={(value) => form.setValue('providerId', value, { shouldDirty: true })}
          options={[none, ...providers.options]}
          isLoading={providers.isLoading}
        />

        <AsyncSelect
          label={t('tours.fields.driver')}
          value={form.watch('driverId')}
          onChange={(value) => form.setValue('driverId', value, { shouldDirty: true })}
          options={[
            none,
            ...(drivers.data?.data ?? []).map((driver) => ({
              value: driver.id,
              label: driver.name,
              hint: driver.code,
            })),
          ]}
          isLoading={drivers.isPending}
        />

        <AsyncSelect
          label={t('tours.fields.vehicle')}
          value={form.watch('vehicleId')}
          onChange={(value) => form.setValue('vehicleId', value, { shouldDirty: true })}
          options={[
            none,
            ...(vehicles.data?.data ?? []).map((vehicle) => ({
              value: vehicle.id,
              label: vehicle.registrationNumber,
              hint: vehicle.code,
            })),
          ]}
          isLoading={vehicles.isPending}
        />
      </div>
    </SectionCard>
  )
}
