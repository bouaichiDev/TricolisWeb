import { useTranslation } from 'react-i18next'

import type { Address } from '../types/address'
import { DetailField } from '@/shared/components/layout/DetailField'
import { SectionCard } from '@/shared/components/layout/SectionCard'

/** Adresse en lecture, telle qu'elle apparait sur une fiche. */
export function AddressCard({ address }: { address: Address }) {
  const { t } = useTranslation()

  const lines = [
    address.addressLine1,
    address.addressLine2,
    [address.postalCode, address.city].filter(Boolean).join(' '),
    address.country,
  ].filter((line): line is string => Boolean(line))

  const window =
    address.timeWindowFrom && address.timeWindowTo
      ? `${address.timeWindowFrom.slice(0, 5)} – ${address.timeWindowTo.slice(0, 5)}`
      : null

  return (
    <SectionCard title={t('addresses.title')}>
      <dl className="grid gap-x-8 sm:grid-cols-2">
        <DetailField label={t('addresses.fields.addressLine1')}>
          <span className="whitespace-pre-line">{lines.join('\n')}</span>
        </DetailField>
        <DetailField label={t('addresses.fields.timeWindow')}>{window}</DetailField>
        <div className="sm:col-span-2">
          <DetailField label={t('addresses.fields.instructions')}>
            {address.instructions}
          </DetailField>
        </div>
      </dl>
    </SectionCard>
  )
}
