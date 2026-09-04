import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import {
  CATALOG_FORM_DEFAULTS,
  catalogSchema,
  type CatalogFormValues,
} from '../schemas/catalogSchema'
import { CATALOG_STATUSES } from '../types/catalog'
import { FormActions } from '@/shared/components/form/FormActions'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { StatusSelect } from '@/shared/components/form/StatusSelect'
import { TextField } from '@/shared/components/form/TextField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { useApiFormError } from '@/shared/hooks/useApiForm'

interface CatalogFormProps {
  defaultValues?: Partial<CatalogFormValues>
  onSubmit: (values: CatalogFormValues) => Promise<unknown>
  onCancel: () => void
  submitLabel: string
}

/**
 * Formulaire d'un catalogue client.
 *
 * Ni version ni période de validité : la ressource ne les expose pas, et le
 * §52 du prompt corrigé interdit de les afficher. Un catalogue est une liste
 * d'articles, datée par ses seules dates de création et de modification.
 */
export function CatalogForm({
  defaultValues,
  onSubmit,
  onCancel,
  submitLabel,
}: CatalogFormProps) {
  const { t } = useTranslation()

  const form = useForm<CatalogFormValues>({
    resolver: zodResolver(catalogSchema),
    defaultValues: { ...CATALOG_FORM_DEFAULTS, ...defaultValues },
  })

  const { formError, handleError, clearError } = useApiFormError(form)

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

      <SectionCard title={t('catalogs.sections.general')}>
        <div className="grid gap-5 sm:grid-cols-2">
          <TextField form={form} name="code" label={t('catalogs.fields.code')} required />
          <TextField form={form} name="name" label={t('catalogs.fields.name')} required />
          <StatusSelect
            form={form}
            name="status"
            label={t('catalogs.fields.status')}
            options={CATALOG_STATUSES}
          />
          <div className="sm:col-span-2">
            <TextField
              form={form}
              name="description"
              label={t('catalogs.fields.description')}
            />
          </div>
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
