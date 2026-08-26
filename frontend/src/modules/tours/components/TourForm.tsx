import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import { useAgencyList } from '@/modules/agencies/hooks/useAgencies'
import { useDepotList } from '@/modules/depots/hooks/useDepots'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { FormActions } from '@/shared/components/form/FormActions'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { TextField } from '@/shared/components/form/TextField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { useApiFormError } from '@/shared/hooks/useApiForm'

import { NONE, optional, tourSchema, type TourFormValues } from './tourFormSchema'
import { TourResourceFields } from './TourResourceFields'
import type { Tour, TourPayload } from '../types/tour'

interface TourFormProps {
  tour?: Tour
  isPending: boolean
  onSubmit: (payload: TourPayload) => Promise<unknown>
  onCancel: () => void
}

/**
 * Saisie d'une tournée.
 *
 * **Le statut ne se choisit pas ici.** Une tournée naît au brouillon et change
 * d'état par les passages du référentiel, depuis sa fiche. Le proposer à la
 * création laisserait créer une tournée « terminée » qui n'a jamais roulé.
 *
 * Le dépôt appartient à l'agence : son sélecteur reste inerte tant qu'aucune
 * agence n'est choisie, faute de quoi il proposerait les dépôts de toutes.
 */
export function TourForm({ tour, isPending, onSubmit, onCancel }: TourFormProps) {
  const { t } = useTranslation()

  const form = useForm<TourFormValues>({
    resolver: zodResolver(tourSchema),
    defaultValues: {
      tourNumber: tour?.tourNumber ?? '',
      tourDate: tour?.tourDate ?? '',
      agencyId: tour?.agencyId ?? '',
      depotId: tour?.depotId ?? NONE,
      providerId: tour?.providerId ?? NONE,
      vehicleId: tour?.vehicleId ?? NONE,
      driverId: tour?.driverId ?? NONE,
      tourType: tour?.tourType ?? '',
      instructions: tour?.instructions ?? '',
      plannedStartAt: tour?.plannedStartAt?.slice(0, 16) ?? '',
      plannedEndAt: tour?.plannedEndAt?.slice(0, 16) ?? '',
    },
  })

  const agencyId = form.watch('agencyId')
  const agencies = useAgencyList({ page: 1, perPage: 100 })
  const depots = useDepotList(agencyId, { page: 1 })

  const { formError, handleError, clearError } = useApiFormError(form)

  const submit = form.handleSubmit(async (values) => {
    clearError()

    try {
      await onSubmit({
        tourNumber: values.tourNumber,
        tourDate: values.tourDate,
        agencyId: values.agencyId,
        depotId: optional(values.depotId),
        providerId: optional(values.providerId),
        vehicleId: optional(values.vehicleId),
        driverId: optional(values.driverId),
        tourType: optional(values.tourType),
        instructions: optional(values.instructions),
        plannedStartAt: optional(values.plannedStartAt),
        plannedEndAt: optional(values.plannedEndAt),
      })
    } catch (error) {
      handleError(error)
    }
  })

  return (
    <form onSubmit={submit} className="flex flex-col gap-6" noValidate>
      <FormErrorSummary message={formError} />

      <SectionCard title={t('tours.form.identity')}>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField form={form} name="tourNumber" label={t('tours.fields.tourNumber')} required />
          <TextField
            form={form}
            name="tourDate"
            type="date"
            label={t('tours.fields.tourDate')}
            required
          />

          <AsyncSelect
            label={t('tours.fields.agency')}
            value={agencyId}
            onChange={(value) => {
              form.setValue('agencyId', value, { shouldDirty: true, shouldValidate: true })
              // Le depot appartient a l'agence : en changer laisserait sinon un
              // depot d'une autre, que le serveur refuserait.
              form.setValue('depotId', NONE, { shouldDirty: true })
            }}
            options={(agencies.data?.data ?? []).map((agency) => ({
              value: agency.id,
              label: agency.name,
              hint: agency.code,
            }))}
            isLoading={agencies.isPending}
            required
            error={form.formState.errors.agencyId?.message}
          />

          <AsyncSelect
            label={t('tours.fields.depot')}
            value={form.watch('depotId')}
            onChange={(value) => form.setValue('depotId', value, { shouldDirty: true })}
            options={[
              { value: NONE, label: t('tours.form.none') },
              ...(depots.data?.data ?? []).map((depot) => ({
                value: depot.id,
                label: depot.name,
                hint: depot.code,
              })),
            ]}
            isLoading={depots.isPending}
            disabled={agencyId === ''}
            description={agencyId === '' ? t('tours.form.pickAgencyFirst') : undefined}
          />
        </div>
      </SectionCard>

      <TourResourceFields form={form} />

      <SectionCard title={t('tours.form.schedule')} description={t('tours.form.scheduleHint')}>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField
            form={form}
            name="plannedStartAt"
            type="datetime-local"
            label={t('tours.fields.plannedStartAt')}
          />
          <TextField
            form={form}
            name="plannedEndAt"
            type="datetime-local"
            label={t('tours.fields.plannedEndAt')}
          />
          <TextField form={form} name="tourType" label={t('tours.fields.tourType')} />
          <TextField form={form} name="instructions" label={t('tours.fields.instructions')} />
        </div>
      </SectionCard>

      <FormActions
        onCancel={onCancel}
        submitLabel={t('common.save')}
        isSubmitting={isPending || form.formState.isSubmitting}
      />
    </form>
  )
}
