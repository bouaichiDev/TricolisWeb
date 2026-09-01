import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import { useCustomerOptions } from '@/modules/orders/hooks/useOrderScope'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { CheckboxField } from '@/shared/components/form/CheckboxField'
import { FormActions } from '@/shared/components/form/FormActions'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import {
  fromJsonText,
  JsonConfigurationEditor,
} from '@/shared/components/form/JsonConfigurationEditor'
import { TextField } from '@/shared/components/form/TextField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { useApiFormError } from '@/shared/hooks/useApiForm'

import { ImportTargetFieldsReference } from './ImportTargetFieldsReference'
import {
  customerImportConfigurationSchema,
  IMPORT_CONFIGURATION_DEFAULTS,
  toImportConfigurationPayload,
  type CustomerImportConfigurationFormValues,
} from '../schemas/customerIntegrationSchemas'
import type { CustomerImportConfigurationPayload } from '../types/customerIntegration'

interface CustomerImportConfigurationFormProps {
  defaultValues?: Partial<CustomerImportConfigurationFormValues>
  onSubmit: (payload: CustomerImportConfigurationPayload) => Promise<unknown>
  onCancel: () => void
  submitLabel: string
  /** En modification : le client est figé, l'API refuse de le changer. */
  lockCustomer?: boolean
}

/**
 * Configuration d'import d'un client.
 *
 * Elle décrit **comment lire** un fichier : d'où il vient, dans quel format, et
 * comment ses colonnes se rattachent au modèle. Elle ne déclenche rien et ne
 * garde aucune trace de lectures passées — il n'existe ni table `Import`, ni
 * route d'exécution, et le §5 interdit d'en simuler une.
 *
 * `sourceType` et `fileFormat` sont des champs libres : aucune énumération ni
 * table ne les contraint côté serveur, et en inventer une liste ici figerait
 * une vérité métier qui n'existe pas (§8, §9).
 *
 * `mapping` et `validationRules` sont saisis en JSON parce que le backend ne
 * fixe **aucune** structure interne — `array|max:500`, sans schéma. Un
 * formulaire à champs supposerait une forme qui casserait au premier client
 * dont le mapping diffère (§12).
 */
export function CustomerImportConfigurationForm({
  defaultValues,
  onSubmit,
  onCancel,
  submitLabel,
  lockCustomer = false,
}: CustomerImportConfigurationFormProps) {
  const { t } = useTranslation()
  const customers = useCustomerOptions('')

  const form = useForm<CustomerImportConfigurationFormValues>({
    resolver: zodResolver(customerImportConfigurationSchema),
    defaultValues: { ...IMPORT_CONFIGURATION_DEFAULTS, ...defaultValues },
  })

  const { formError, handleError, clearError, setFormError } = useApiFormError(form)

  const submit = form.handleSubmit(async (values) => {
    clearError()

    // `fromJsonText` rend `undefined` quand le texte n'est pas un objet JSON
    // valide. Envoyer la chaîne telle quelle produirait un 422 sur un champ que
    // l'écran n'aurait pas signalé.
    const mapping = fromJsonText(values.mapping)
    const validationRules = fromJsonText(values.validationRules)

    if (mapping === undefined || validationRules === undefined) {
      setFormError(t('json.invalidPayload'))

      return
    }

    try {
      await onSubmit(toImportConfigurationPayload(values, mapping, validationRules))
    } catch (error) {
      handleError(error)
    }
  })

  return (
    <form onSubmit={submit} className="flex flex-col gap-6" noValidate>
      <FormErrorSummary message={formError} />

      <SectionCard
        title={t('integrations.imports.sections.general')}
        description={t('integrations.imports.generalHint')}
      >
        <div className="grid gap-5 sm:grid-cols-2">
          <AsyncSelect
            label={t('integrations.fields.customer')}
            value={form.watch('customerId')}
            onChange={(next) =>
              form.setValue('customerId', next, { shouldDirty: true, shouldValidate: true })
            }
            options={customers.options}
            isLoading={customers.isLoading}
            disabled={lockCustomer}
            required
            description={lockCustomer ? t('integrations.customerLocked') : undefined}
            error={form.formState.errors.customerId?.message}
          />

          <TextField
            form={form}
            name="name"
            label={t('integrations.fields.name')}
            required
            description={t('integrations.imports.nameHint')}
          />

          <TextField
            form={form}
            name="sourceType"
            label={t('integrations.fields.sourceType')}
            required
            description={t('integrations.imports.sourceTypeHint')}
          />

          <TextField
            form={form}
            name="fileFormat"
            label={t('integrations.fields.fileFormat')}
            required
            description={t('integrations.imports.fileFormatHint')}
          />
        </div>

        <div className="mt-4">
          <CheckboxField
            form={form}
            name="isActive"
            label={t('integrations.fields.isActive')}
            description={t('integrations.imports.activeHint')}
          />
        </div>
      </SectionCard>

      <SectionCard
        title={t('integrations.imports.sections.mapping')}
        description={t('integrations.imports.mappingSectionHint')}
      >
        <div className="flex flex-col gap-5">
          <ImportTargetFieldsReference />

          <JsonConfigurationEditor
            label={t('integrations.fields.mapping')}
            value={form.watch('mapping')}
            onChange={(next) => form.setValue('mapping', next, { shouldDirty: true })}
            description={t('integrations.imports.mappingHint')}
            initialValue={defaultValues?.mapping ?? ''}
          />

          <JsonConfigurationEditor
            label={t('integrations.fields.validationRules')}
            value={form.watch('validationRules')}
            onChange={(next) => form.setValue('validationRules', next, { shouldDirty: true })}
            description={t('integrations.imports.validationRulesHint')}
            initialValue={defaultValues?.validationRules ?? ''}
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
