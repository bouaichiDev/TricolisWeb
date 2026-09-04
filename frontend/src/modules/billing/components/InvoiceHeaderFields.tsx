import { useTranslation } from 'react-i18next'

import { useCustomerList } from '@/modules/customers/hooks/useCustomers'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { Textarea } from '@/shared/components/ui/textarea'

export interface InvoiceHeaderState {
  customerId: string
  invoiceNumber: string
  invoiceDate: string
  currencyCode: string
  externalReference: string
  remark: string
}

interface InvoiceHeaderFieldsProps {
  value: InvoiceHeaderState
  onChange: (next: InvoiceHeaderState) => void
  /** Le client fige la sélection déjà faite : on ne le change plus en route. */
  customerLocked: boolean
}

/**
 * L'en-tête d'une facture en préparation.
 *
 * **Les deux numéros sont facultatifs.** Laissés vides, le serveur les
 * attribue : `INV-2026-000001`, et la référence externe reprend le même. Les
 * exiger obligeait à inventer un numéro unique à chaque facture, et l'unicité
 * ne se découvrait qu'à l'enregistrement, la facture déjà composée.
 *
 * Un numéro saisi est conservé : une organisation qui tient sa propre série
 * doit pouvoir la continuer.
 *
 * La devise est celle de la facture entière ; le diagramme n'en porte qu'une,
 * et mélanger deux monnaies sur un même document n'aurait pas de total.
 */
export function InvoiceHeaderFields({ value, onChange, customerLocked }: InvoiceHeaderFieldsProps) {
  const { t } = useTranslation()
  const customers = useCustomerList({ page: 1, perPage: 100 })

  const set = (patch: Partial<InvoiceHeaderState>) => onChange({ ...value, ...patch })

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <AsyncSelect
        label={t('billing.invoices.fields.customer')}
        value={value.customerId}
        onChange={(customerId) => set({ customerId })}
        options={(customers.data?.data ?? []).map((customer) => ({
          value: customer.id,
          label: customer.name,
          hint: customer.code,
        }))}
        isLoading={customers.isPending}
        disabled={customerLocked}
        description={customerLocked ? t('billing.invoices.customerLocked') : undefined}
      />

      <div className="flex flex-col gap-2">
        <Label htmlFor="invoice-number">{t('billing.invoices.fields.invoiceNumber')}</Label>
        <Input
          id="invoice-number"
          value={value.invoiceNumber}
          onChange={(event) => set({ invoiceNumber: event.target.value })}
          placeholder={t('billing.invoices.autoNumber')}
        />
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="invoice-date">{t('billing.invoices.fields.invoiceDate')}</Label>
        <Input
          id="invoice-date"
          type="date"
          value={value.invoiceDate}
          onChange={(event) => set({ invoiceDate: event.target.value })}
          required
        />
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="invoice-currency">{t('billing.invoices.fields.currency')}</Label>
        <Input
          id="invoice-currency"
          value={value.currencyCode}
          onChange={(event) => set({ currencyCode: event.target.value.toUpperCase() })}
          maxLength={3}
          className="w-28"
          required
        />
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="invoice-external">{t('billing.invoices.fields.externalReference')}</Label>
        <Input
          id="invoice-external"
          value={value.externalReference}
          onChange={(event) => set({ externalReference: event.target.value })}
          placeholder={t('billing.invoices.autoNumber')}
        />
      </div>

      <div className="flex flex-col gap-2 sm:col-span-2">
        <Label htmlFor="invoice-remark">{t('billing.invoices.fields.remark')}</Label>
        <Textarea
          id="invoice-remark"
          value={value.remark}
          onChange={(event) => set({ remark: event.target.value })}
          rows={2}
        />
      </div>
    </div>
  )
}
