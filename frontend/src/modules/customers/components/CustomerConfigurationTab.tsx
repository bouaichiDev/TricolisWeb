import { useTranslation } from 'react-i18next'

import { CustomerCapabilities } from './CustomerCapabilities'
import { CustomerBillingTemplate } from './CustomerBillingTemplate'
import type { Customer } from '../types/customer'
import { DetailField } from '@/shared/components/layout/DetailField'
import { SectionCard } from '@/shared/components/layout/SectionCard'

export function CustomerConfigurationTab({ customer }: { customer: Customer }) {
  const { t } = useTranslation()

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      <SectionCard title={t('customers.sections.configuration')}>
        <dl>
          <DetailField label={t('customers.fields.paymentMode')}>
            {customer.paymentMode}
          </DetailField>
          <DetailField label={t('customers.fields.communicationMode')}>
            {customer.communicationMode}
          </DetailField>
        </dl>
      </SectionCard>

      <SectionCard
        title={t('customers.capabilities')}
        description={t('customers.capabilitiesHint')}
      >
        <CustomerCapabilities customer={customer} />
      </SectionCard>

      <CustomerBillingTemplate customer={customer} />
    </div>
  )
}
