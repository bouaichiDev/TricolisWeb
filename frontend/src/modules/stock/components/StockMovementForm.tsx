import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { ControlledField } from '@/shared/components/form/ControlledField'
import { FormActions } from '@/shared/components/form/FormActions'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { TextField } from '@/shared/components/form/TextField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { useApiFormError } from '@/shared/hooks/useApiForm'

import { CustomerFilterSelect } from './CustomerFilterSelect'
import { useStockItemOptions, useStockLocationOptions } from '../hooks/useStockScope'
import {
  STOCK_MOVEMENT_FORM_DEFAULTS,
  stockMovementSchema,
  type StockMovementFormValues,
} from '../schemas/stockMovementSchema'
import type { MovementDirection } from '../types/stock'

const DIRECTIONS: MovementDirection[] = ['entry', 'exit', 'transfer']

interface StockMovementFormProps {
  onSubmit: (values: StockMovementFormValues) => Promise<unknown>
  onCancel: () => void
  submitLabel: string
}

/**
 * Enregistrer un mouvement — le seul moyen de faire bouger une quantité.
 *
 * Le **sens** n'est pas un champ du modèle : il se déduit des emplacements.
 * Entrée = destination seule, sortie = source seule, transfert = les deux.
 * L'écran le demande d'abord parce que c'est la question qu'on se pose, puis
 * n'envoie que ce que le sens implique.
 *
 * Le client n'est pas envoyé non plus : il ne sert qu'à restreindre les articles
 * proposés. Le serveur le déduit de l'article, et refuserait de toute façon un
 * article d'une autre organisation.
 *
 * Un transfert est **une** soumission : `CreateStockMovementAction` débite et
 * crédite dans la même transaction. Deux requêtes laisseraient une fenêtre où
 * la marchandise n'existe nulle part.
 */
export function StockMovementForm({ onSubmit, onCancel, submitLabel }: StockMovementFormProps) {
  const { t } = useTranslation()

  const [customerId, setCustomerId] = useState<string | undefined>(undefined)
  const [direction, setDirection] = useState<MovementDirection>('entry')
  const [locationSearch, setLocationSearch] = useState('')

  const items = useStockItemOptions(customerId ?? '')
  const locations = useStockLocationOptions(locationSearch)

  const form = useForm<StockMovementFormValues>({
    resolver: zodResolver(stockMovementSchema),
    defaultValues: STOCK_MOVEMENT_FORM_DEFAULTS,
  })

  const { formError, handleError, clearError } = useApiFormError(form)

  const needsSource = direction !== 'entry'
  const needsDestination = direction !== 'exit'

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

      <SectionCard title={t('stock.sections.what')} description={t('stock.movementWhatHint')}>
        <div className="grid gap-5 sm:grid-cols-2">
          <div className="flex flex-col gap-2">
            <span className="text-sm font-medium">{t('stock.fields.customer')}</span>
            <CustomerFilterSelect
              value={customerId}
              onChange={(next) => {
                setCustomerId(next)
                // Le client change : l'article retenu ne lui appartient plus.
                form.setValue('stockItemId', '')
              }}
              className="w-full"
            />
            <p className="text-xs text-muted-foreground">{t('stock.movementCustomerHint')}</p>
          </div>

          <AsyncSelect
            label={t('stock.fields.articleCode')}
            value={form.watch('stockItemId')}
            onChange={(next) =>
              form.setValue('stockItemId', next, { shouldDirty: true, shouldValidate: true })
            }
            options={items.options}
            isLoading={items.isLoading}
            disabled={customerId === undefined}
            description={
              customerId === undefined ? t('stock.pickCustomerFirst') : undefined
            }
            required
            error={form.formState.errors.stockItemId?.message}
          />

          <TextField
            form={form}
            name="quantity"
            label={t('stock.fields.quantity')}
            type="number"
            required
          />

          <TextField
            form={form}
            name="movementType"
            label={t('stock.fields.movementType')}
            required
            description={t('stock.movementTypeFreeHint')}
          />
        </div>
      </SectionCard>

      <SectionCard title={t('stock.sections.where')} description={t('stock.movementWhereHint')}>
        <div className="grid gap-5 sm:grid-cols-2">
          <AsyncSelect
            label={t('stock.direction')}
            value={direction}
            onChange={(next) => {
              setDirection(next as MovementDirection)
              // Un emplacement retenu pour l'autre sens partirait dans une
              // charge utile qui ne l'attend plus.
              form.setValue('sourceLocationId', '')
              form.setValue('destinationLocationId', '')
            }}
            options={DIRECTIONS.map((value) => ({
              value,
              label: t(`stock.directions.${value}`),
              hint: t(`stock.directionHints.${value}`),
            }))}
            required
          />

          <ControlledField
            label={t('stock.filterLocations')}
            value={locationSearch}
            onChange={setLocationSearch}
            description={
              locations.isTruncated
                ? t('stock.locationsTruncated', {
                    shown: locations.options.length,
                    total: locations.total,
                  })
                : t('stock.filterLocationsHint')
            }
          />

          {needsSource ? (
            <AsyncSelect
              label={t('stock.sourceLocation')}
              value={form.watch('sourceLocationId')}
              onChange={(next) =>
                form.setValue('sourceLocationId', next, { shouldValidate: true })
              }
              options={locations.options}
              isLoading={locations.isLoading}
              required
              error={form.formState.errors.sourceLocationId?.message}
            />
          ) : null}

          {needsDestination ? (
            <AsyncSelect
              label={t('stock.destinationLocation')}
              value={form.watch('destinationLocationId')}
              onChange={(next) =>
                form.setValue('destinationLocationId', next, { shouldValidate: true })
              }
              options={locations.options}
              isLoading={locations.isLoading}
              required
              description={direction === 'transfer' ? t('stock.sameDepotHint') : undefined}
              error={form.formState.errors.destinationLocationId?.message}
            />
          ) : null}
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
