import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import { useAgencyOptions, useDepotOptions } from '@/modules/orders/hooks/useOrderScope'
import { ReferentialStatusSelect } from '@/modules/statuses/components/ReferentialStatusSelect'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { FormActions } from '@/shared/components/form/FormActions'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { TextField } from '@/shared/components/form/TextField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { useApiFormError } from '@/shared/hooks/useApiForm'

import { useParentLocationOptions } from '../hooks/useStockScope'
import {
  STOCK_LOCATION_FORM_DEFAULTS,
  stockLocationSchema,
  type StockLocationFormValues,
} from '../schemas/stockLocationSchema'
import { STOCK_LOCATION_SOURCE } from '../utils/stockSources'

const COORDINATES = ['zoneCode', 'aisle', 'rack', 'level'] as const

interface StockLocationFormProps {
  defaultValues?: Partial<StockLocationFormValues>
  onSubmit: (values: StockLocationFormValues) => Promise<unknown>
  onCancel: () => void
  submitLabel: string
  /** En modification : le dépôt est figé, l'API refuse de le changer. */
  lockDepot?: boolean
  /** Emplacement en cours d'édition, exclu de ses propres parents possibles. */
  currentId?: string
}

/**
 * Formulaire d'emplacement.
 *
 * **Pas de client.** Un emplacement est une étagère : il appartient au dépôt du
 * transporteur, et la marchandise de plusieurs clients y passe. C'est
 * `StockBalance` qui rattache une quantité à un article, donc à un client.
 *
 * Le dépôt se choisit par son agence, parce que `/agencies/{agency}/depots` est
 * la seule route qui les liste. En modification il est verrouillé : un
 * emplacement physique ne déménage pas, et `UpdateStockLocationRequest` ne
 * connaît pas le champ.
 *
 * Le parent n'est proposé que dans le même dépôt : au-delà,
 * `ValidateStockLocationHierarchy` refuserait. Les cycles plus longs ne sont pas
 * filtrés ici — les détecter demanderait de descendre l'arbre entier à chaque
 * frappe, et le serveur reste l'autorité.
 */
export function StockLocationForm({
  defaultValues,
  onSubmit,
  onCancel,
  submitLabel,
  lockDepot = false,
  currentId,
}: StockLocationFormProps) {
  const { t } = useTranslation()
  const [agencyId, setAgencyId] = useState('')

  const form = useForm<StockLocationFormValues>({
    resolver: zodResolver(stockLocationSchema),
    defaultValues: { ...STOCK_LOCATION_FORM_DEFAULTS, ...defaultValues },
  })

  const { formError, handleError, clearError } = useApiFormError(form)
  const depotId = form.watch('depotId')

  const agencies = useAgencyOptions()
  const depots = useDepotOptions(agencyId)
  const parents = useParentLocationOptions(depotId, currentId)

  const submit = form.handleSubmit(async (values) => {
    clearError()
    try {
      await onSubmit(values)
    } catch (error) {
      handleError(error)
    }
  })

  return (
    <form onSubmit={submit} className="flex flex-col gap-6" noValidate>
      <FormErrorSummary message={formError} />

      <SectionCard title={t('stock.sections.place')} description={t('stock.locationHint')}>
        <div className="grid gap-5 sm:grid-cols-2">
          {lockDepot ? null : (
            <>
              <AsyncSelect
                label={t('orders.fields.agency')}
                value={agencyId}
                onChange={(next) => {
                  setAgencyId(next)
                  form.setValue('depotId', '')
                  form.setValue('parentLocationId', '')
                }}
                options={agencies.options}
                isLoading={agencies.isLoading}
                required
              />

              <AsyncSelect
                label={t('orders.fields.depot')}
                value={depotId}
                onChange={(next) => {
                  form.setValue('depotId', next, { shouldDirty: true, shouldValidate: true })
                  // Le dépôt change : le parent retenu n'y est plus, et le
                  // serveur refuserait un parent d'un autre dépôt.
                  form.setValue('parentLocationId', '')
                }}
                options={depots.options}
                isLoading={depots.isLoading}
                disabled={agencyId === ''}
                description={agencyId === '' ? t('stock.pickAgencyFirst') : undefined}
                required
                error={form.formState.errors.depotId?.message}
              />
            </>
          )}

          <AsyncSelect
            label={t('stock.fields.parent')}
            value={form.watch('parentLocationId') === '' ? 'none' : form.watch('parentLocationId')}
            onChange={(next) =>
              form.setValue('parentLocationId', next === 'none' ? '' : next, {
                shouldDirty: true,
              })
            }
            options={[{ value: 'none', label: t('stock.noParent') }, ...parents.options]}
            isLoading={parents.isLoading}
            disabled={depotId === ''}
            description={
              parents.isTruncated ? t('stock.parentsTruncated') : t('stock.parentHint')
            }
          />

          <TextField
            form={form}
            name="locationCode"
            label={t('stock.fields.locationCode')}
            required
            description={t('stock.locationCodeHint')}
          />

          <TextField
            form={form}
            name="barcode"
            label={t('stock.fields.barcode')}
          />

          <ReferentialStatusSelect
            form={form}
            name="status"
            label={t('stock.fields.status')}
            source={STOCK_LOCATION_SOURCE}
          />
        </div>
      </SectionCard>

      <SectionCard
        title={t('stock.sections.coordinates')}
        description={t('stock.coordinatesHint')}
      >
        <div className="grid gap-5 sm:grid-cols-4">
          {COORDINATES.map((field) => (
            <TextField
              key={field}
              form={form}
              name={field}
              label={t(`stock.fields.${field}`)}
            />
          ))}
        </div>
      </SectionCard>

      <FormActions
        onCancel={onCancel}
        submitLabel={submitLabel}
        isSubmitting={form.formState.isSubmitting}
      />
    </form>
  )
}
