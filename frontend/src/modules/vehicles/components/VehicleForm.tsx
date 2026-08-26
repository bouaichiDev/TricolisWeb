import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'

import { useProviderOptions } from '@/modules/providers/hooks/useProviders'
import { ReferentialStatusSelect } from '@/modules/statuses/components/ReferentialStatusSelect'
import { useTypeItemOptions } from '@/modules/types/hooks/useTypes'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { TextField } from '@/shared/components/form/TextField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { useApiFormError } from '@/shared/hooks/useApiForm'

import type { Vehicle, VehiclePayload } from '../types/vehicle'

/**
 * Une capacité saisie reste une chaîne jusqu'au dernier moment.
 *
 * `z.coerce.number()` transformerait un champ laissé vide en `0` et
 * l'enregistrerait sans rien dire. On valide donc le texte, puis on convertit à
 * l'envoi : un champ vide est une erreur, pas une charge utile nulle.
 */
/** Sentinelle du choix « aucun » : Radix refuse une option de valeur vide. */
const NONE = 'none'

const capacity = (message: string) =>
  z
    .string()
    .min(1, 'validation.required')
    .refine((value) => !Number.isNaN(Number(value)) && Number(value) >= 0, message)

/** Contraintes reprises de `StoreVehicleRequest`. */
export const vehicleSchema = z.object({
  providerId: z.string(),
  vehicleTypeId: z.string().min(1, 'validation.required'),
  code: z.string().min(1, 'validation.required').max(64, 'validation.max'),
  registrationNumber: z.string().min(1, 'validation.required').max(32, 'validation.max'),
  payloadCapacity: capacity('validation.positiveNumber'),
  volumeCapacity: capacity('validation.positiveNumber'),
  palletCapacity: capacity('validation.positiveNumber'),
  status: z.string().min(1, 'validation.required'),
})

export type VehicleFormValues = z.infer<typeof vehicleSchema>

interface VehicleFormProps {
  vehicle?: Vehicle
  /** Prérempli à la création depuis la fiche d'un fournisseur. */
  providerId?: string
  isPending: boolean
  onSubmit: (payload: VehiclePayload) => Promise<unknown>
  onCancel: () => void
}

/**
 * Saisie d'un véhicule.
 *
 * Le type vient du référentiel `vehicle` de `type_items` : les types de colis
 * et de groupage partagent la même table, et `useTypeItemOptions('vehicle')` ne
 * remonte que ceux qui conviennent. Le serveur revérifie la provenance.
 *
 * Le fournisseur est facultatif : un transporteur possède ses propres camions,
 * et l'organisation du véhicule est alors portée par le véhicule lui-même.
 */
export function VehicleForm({
  vehicle,
  providerId,
  isPending,
  onSubmit,
  onCancel,
}: VehicleFormProps) {
  const { t } = useTranslation()
  const providers = useProviderOptions()
  const types = useTypeItemOptions('vehicle')

  const form = useForm<VehicleFormValues>({
    resolver: zodResolver(vehicleSchema),
    defaultValues: {
      providerId: vehicle?.providerId ?? providerId ?? NONE,
      vehicleTypeId: vehicle?.vehicleTypeId ?? '',
      code: vehicle?.code ?? '',
      registrationNumber: vehicle?.registrationNumber ?? '',
      payloadCapacity: String(vehicle?.payloadCapacity ?? ''),
      volumeCapacity: String(vehicle?.volumeCapacity ?? ''),
      palletCapacity: String(vehicle?.palletCapacity ?? ''),
      status: vehicle?.status ?? 'active',
    },
  })

  const { formError, handleError, clearError } = useApiFormError(form)

  const submit = form.handleSubmit(async (values) => {
    clearError()
    try {
      await onSubmit({
        ...values,
        providerId: values.providerId === NONE ? null : values.providerId,
        payloadCapacity: Number(values.payloadCapacity),
        volumeCapacity: Number(values.volumeCapacity),
        palletCapacity: Number(values.palletCapacity),
      })
    } catch (error) {
      handleError(error)
    }
  })

  return (
    <form onSubmit={submit} className="flex flex-col gap-6" noValidate>
      <FormErrorSummary message={formError} />

      <SectionCard title={t('vehicles.identity')}>
        <div className="grid gap-4 sm:grid-cols-2">
          <AsyncSelect
            label={t('vehicles.fields.provider')}
            value={form.watch('providerId')}
            onChange={(value) =>
              form.setValue('providerId', value, { shouldDirty: true, shouldValidate: true })
            }
            options={[{ value: NONE, label: t('vehicles.ownVehicle') }, ...providers.options]}
            isLoading={providers.isLoading}
            error={form.formState.errors.providerId?.message}
          />

          <AsyncSelect
            label={t('vehicles.fields.vehicleType')}
            value={form.watch('vehicleTypeId')}
            onChange={(value) =>
              form.setValue('vehicleTypeId', value, { shouldDirty: true, shouldValidate: true })
            }
            options={types.options}
            isLoading={types.isLoading}
            required
            error={form.formState.errors.vehicleTypeId?.message}
          />

          <TextField form={form} name="code" label={t('vehicles.fields.code')} required />
          <TextField
            form={form}
            name="registrationNumber"
            label={t('vehicles.fields.registrationNumber')}
            required
          />

          <ReferentialStatusSelect
            form={form}
            name="status"
            label={t('vehicles.fields.status')}
            source="vehicle"
          />
        </div>
      </SectionCard>

      <SectionCard title={t('vehicles.capacities')} description={t('vehicles.capacitiesHint')}>
        <div className="grid gap-4 sm:grid-cols-3">
          <TextField
            form={form}
            name="payloadCapacity"
            type="number"
            label={t('vehicles.fields.payloadCapacity')}
            required
          />
          <TextField
            form={form}
            name="volumeCapacity"
            type="number"
            label={t('vehicles.fields.volumeCapacity')}
            required
          />
          <TextField
            form={form}
            name="palletCapacity"
            type="number"
            label={t('vehicles.fields.palletCapacity')}
            required
          />
        </div>
      </SectionCard>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={onCancel}>
          {t('common.cancel')}
        </Button>
        <Button type="submit" disabled={isPending || form.formState.isSubmitting}>
          {isPending ? t('common.saving') : t('common.save')}
        </Button>
      </div>
    </form>
  )
}
