import { zodResolver } from '@hookform/resolvers/zod'
import { Loader2 } from 'lucide-react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import {
  CUSTOMER_FORM_DEFAULTS,
  customerSchema,
  type CustomerFormValues,
} from '../schemas/customerSchema'
import { CUSTOMER_CAPABILITIES } from '../types/customer'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { TextField } from '@/shared/components/form/TextField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { Label } from '@/shared/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/components/ui/select'
import { Switch } from '@/shared/components/ui/switch'
import { useApiFormError } from '@/shared/hooks/useApiForm'

const STATUSES = ['active', 'inactive', 'blocked'] as const

interface CustomerFormProps {
  defaultValues?: Partial<CustomerFormValues>
  onSubmit: (values: CustomerFormValues) => Promise<unknown>
  onCancel: () => void
  submitLabel: string
  /** Le code identifie le client pour les intégrations : figé après création. */
  lockCode?: boolean
}

/**
 * Formulaire client, en sections (§19).
 *
 * Le formulaire vit hors de la page : création et modification l'utilisent tel
 * quel, avec des valeurs par défaut différentes. Le §19 l'exige — « Ne pas
 * mettre tout le formulaire dans la Page ».
 */
export function CustomerForm({
  defaultValues,
  onSubmit,
  onCancel,
  submitLabel,
  lockCode = false,
}: CustomerFormProps) {
  const { t } = useTranslation()

  const form = useForm<CustomerFormValues>({
    resolver: zodResolver(customerSchema),
    defaultValues: { ...CUSTOMER_FORM_DEFAULTS, ...defaultValues },
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

      <SectionCard title={t('customers.sections.general')}>
        <div className="grid gap-5 sm:grid-cols-2">
          <TextField
            form={form}
            name="code"
            label={t('customers.fields.code')}
            required
            disabled={lockCode}
            description={lockCode ? t('customers.codeLocked') : undefined}
          />
          <TextField form={form} name="name" label={t('customers.fields.name')} required />
          <TextField form={form} name="legalName" label={t('customers.fields.legalName')} />
          <TextField form={form} name="email" label={t('customers.fields.email')} type="email" />
          <TextField form={form} name="phone" label={t('customers.fields.phone')} />

          <div className="flex flex-col gap-2">
            <Label htmlFor="status">{t('customers.fields.status')}</Label>
            <Select
              value={form.watch('status')}
              onValueChange={(value) => form.setValue('status', value, { shouldDirty: true })}
            >
              <SelectTrigger id="status">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {STATUSES.map((status) => (
                  <SelectItem key={status} value={status}>
                    {t(`status.${status}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>
      </SectionCard>

      <SectionCard title={t('customers.sections.configuration')}>
        <div className="grid gap-5 sm:grid-cols-2">
          <TextField
            form={form}
            name="paymentMode"
            label={t('customers.fields.paymentMode')}
            description={t('customers.freeTextHint')}
          />
          <TextField
            form={form}
            name="communicationMode"
            label={t('customers.fields.communicationMode')}
            description={t('customers.freeTextHint')}
          />
        </div>
      </SectionCard>

      <SectionCard
        title={t('customers.capabilities')}
        description={t('customers.capabilitiesHint')}
      >
        <div className="divide-y">
          {CUSTOMER_CAPABILITIES.map((capability) => (
            <div key={capability} className="flex items-center justify-between py-3">
              <Label htmlFor={capability} className="cursor-pointer font-normal">
                {t(`customers.capability.${capability}`)}
              </Label>
              <Switch
                id={capability}
                checked={form.watch(capability)}
                onCheckedChange={(checked) =>
                  form.setValue(capability, checked, { shouldDirty: true })
                }
              />
            </div>
          ))}
        </div>
      </SectionCard>

      <div className="flex justify-end gap-3">
        <Button type="button" variant="outline" onClick={onCancel}>
          {t('common.cancel')}
        </Button>
        <Button type="submit" disabled={form.formState.isSubmitting}>
          {form.formState.isSubmitting ? (
            <Loader2 className="size-4 animate-spin" aria-hidden />
          ) : null}
          {submitLabel}
        </Button>
      </div>
    </form>
  )
}
