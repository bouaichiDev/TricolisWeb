import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import { useCustomerOptions } from '@/modules/orders/hooks/useOrderScope'
import { ReferentialStatusSelect } from '@/modules/statuses/components/ReferentialStatusSelect'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { FormActions } from '@/shared/components/form/FormActions'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { TextField } from '@/shared/components/form/TextField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { useApiFormError } from '@/shared/hooks/useApiForm'

import { CatalogItemPicker } from './CatalogItemPicker'
import {
  STOCK_ITEM_FORM_DEFAULTS,
  stockItemSchema,
  type StockItemFormValues,
} from '../schemas/stockItemSchema'
import { STOCK_ITEM_SOURCE } from '../utils/stockSources'

interface StockItemFormProps {
  defaultValues?: Partial<StockItemFormValues>
  onSubmit: (values: StockItemFormValues) => Promise<unknown>
  onCancel: () => void
  submitLabel: string
  /** En modification : le client est figé, l'API refuse de le changer. */
  lockCustomer?: boolean
}

/**
 * Formulaire d'article de stock.
 *
 * **Aucun champ de quantité, et c'est structurel.** Les quantités vivent dans
 * `stock_balances`, par emplacement : le même article peut dormir dans trois
 * endroits avec trois soldes différents. Une quantité posée ici serait une
 * quatrième valeur que rien ne tiendrait à jour.
 *
 * Le client est verrouillé en modification parce que `UpdateStockItemRequest`
 * ne connaît pas le champ : déplacer un article d'un client à l'autre
 * emporterait ses soldes, ses mouvements et ses réservations avec lui.
 */
export function StockItemForm({
  defaultValues,
  onSubmit,
  onCancel,
  submitLabel,
  lockCustomer = false,
}: StockItemFormProps) {
  const { t } = useTranslation()
  const customers = useCustomerOptions('')

  const form = useForm<StockItemFormValues>({
    resolver: zodResolver(stockItemSchema),
    defaultValues: { ...STOCK_ITEM_FORM_DEFAULTS, ...defaultValues },
  })

  const { formError, handleError, clearError } = useApiFormError(form)
  const customerId = form.watch('customerId')

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

      <SectionCard title={t('stock.sections.identity')} description={t('stock.itemFormHint')}>
        <div className="grid gap-5 sm:grid-cols-2">
          <AsyncSelect
            label={t('stock.fields.customer')}
            value={customerId}
            onChange={(next) => {
              form.setValue('customerId', next, { shouldDirty: true, shouldValidate: true })
              // Le client change : son catalogue n'est plus le même, et lier
              // l'article d'un autre client serait refusé par le serveur.
              form.setValue('catalogItemId', '')
            }}
            options={customers.options}
            isLoading={customers.isLoading}
            disabled={lockCustomer}
            required
            description={lockCustomer ? t('stock.customerLocked') : undefined}
            error={form.formState.errors.customerId?.message}
          />

          <TextField
            form={form}
            name="articleCode"
            label={t('stock.fields.articleCode')}
            required
            description={t('stock.articleCodeHint')}
          />

          <TextField
            form={form}
            name="barcode"
            label={t('stock.fields.barcode')}
            description={t('stock.barcodeHint')}
          />

          <ReferentialStatusSelect
            form={form}
            name="status"
            label={t('stock.fields.status')}
            source={STOCK_ITEM_SOURCE}
          />

          <TextField
            form={form}
            name="description"
            label={t('stock.fields.description')}
          />
        </div>
      </SectionCard>

      <SectionCard title={t('stock.sections.catalogLink')} description={t('stock.catalogLinkHint')}>
        <div className="grid gap-5 sm:grid-cols-2">
          <CatalogItemPicker
            customerId={customerId}
            value={form.watch('catalogItemId')}
            onChange={(next) => form.setValue('catalogItemId', next, { shouldDirty: true })}
          />
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
