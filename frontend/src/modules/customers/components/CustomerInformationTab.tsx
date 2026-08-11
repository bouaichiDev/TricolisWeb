import { useTranslation } from 'react-i18next'

import type { Customer } from '../types/customer'
import { DetailField } from '@/shared/components/layout/DetailField'
import { SectionCard } from '@/shared/components/layout/SectionCard'

export function CustomerInformationTab({ customer }: { customer: Customer }) {
  const { t } = useTranslation()

  return (
    <SectionCard title={t('customers.sections.general')}>
      <dl className="grid gap-x-8 sm:grid-cols-2">
        <DetailField label={t('customers.fields.code')}>{customer.code}</DetailField>
        <DetailField label={t('customers.fields.name')}>{customer.name}</DetailField>
        <DetailField label={t('customers.fields.legalName')}>{customer.legalName}</DetailField>
        <DetailField label={t('customers.fields.email')}>
          {customer.email ? (
            <a href={`mailto:${customer.email}`} className="text-primary hover:underline">
              {customer.email}
            </a>
          ) : null}
        </DetailField>
        <DetailField label={t('customers.fields.phone')}>{customer.phone}</DetailField>
      </dl>
    </SectionCard>
  )
}
