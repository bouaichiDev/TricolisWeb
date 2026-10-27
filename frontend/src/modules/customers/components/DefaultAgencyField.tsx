import { useTranslation } from 'react-i18next'
import type { UseFormReturn } from 'react-hook-form'

import type { CustomerFormValues } from '../schemas/customerSchema'
import { useAgencyList } from '@/modules/agencies/hooks/useAgencies'
import { Label } from '@/shared/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/components/ui/select'

/** Valeur interne du choix « aucune » : Radix refuse une option à valeur vide. */
const AUTO = '__auto__'

/**
 * L'agence qui reçoit les commandes du client passées depuis le portail.
 * Vide, c'est la première agence active de l'organisation.
 */
export function DefaultAgencyField({ form }: { form: UseFormReturn<CustomerFormValues> }) {
  const { t } = useTranslation()
  const { data } = useAgencyList({ page: 1, perPage: 100, sort: 'name', direction: 'asc' })
  const value = form.watch('defaultAgencyId') || AUTO

  return (
    <div className="flex flex-col gap-2">
      <Label htmlFor="defaultAgencyId">{t('customers.fields.defaultAgency')}</Label>
      <Select
        value={value}
        onValueChange={(next) => form.setValue('defaultAgencyId', next === AUTO ? '' : next, { shouldDirty: true })}
      >
        <SelectTrigger id="defaultAgencyId">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={AUTO}>{t('customers.defaultAgencyAuto')}</SelectItem>
          {(data?.data ?? []).map((agency) => (
            <SelectItem key={agency.id} value={agency.id}>
              {agency.name}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      <p className="text-xs text-muted-foreground">{t('customers.defaultAgencyHint')}</p>
    </div>
  )
}
