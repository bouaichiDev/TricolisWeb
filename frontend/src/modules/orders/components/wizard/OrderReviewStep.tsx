import { useTranslation } from 'react-i18next'

import { DetailField } from '@/shared/components/layout/DetailField'
import { SectionCard } from '@/shared/components/layout/SectionCard'

import type { OrderDraft } from '../../schemas/orderDraft'
import { packageTree } from '../../schemas/packageOrder'

interface OrderReviewStepProps {
  draft: OrderDraft
  customerName: string
  agencyName: string
}

const dash = '—'
const shown = (value: string): string => (value.trim() === '' ? dash : value)

/**
 * Dernier coup d'œil avant envoi.
 *
 * La commande est créée en une seule requête transactionnelle : ce qui est
 * listé ici part ensemble, ou rien ne part. Le récapitulatif compte donc ce qui
 * sera réellement envoyé, pas ce qui a été ouvert dans le formulaire.
 */
export function OrderReviewStep({ draft, customerName, agencyName }: OrderReviewStepProps) {
  const { t } = useTranslation()
  const nodes = packageTree(draft.packages)

  return (
    <div className="flex flex-col gap-4">
      <SectionCard title={t('orders.review.header')} description={t('orders.review.description')}>
        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <DetailField label={t('orders.fields.customer')}>{shown(customerName)}</DetailField>
          <DetailField label={t('orders.fields.agency')}>{shown(agencyName)}</DetailField>
          <DetailField label={t('orders.fields.orderDate')}>{shown(draft.orderDate)}</DetailField>
          <DetailField label={t('orders.fields.externalReference')}>
            {shown(draft.externalReference)}
          </DetailField>
          <DetailField label={t('orders.fields.customerReference')}>
            {shown(draft.customerReference)}
          </DetailField>
          <DetailField label={t('orders.fields.currencyCode')}>{shown(draft.currencyCode)}</DetailField>
        </dl>
      </SectionCard>

      <SectionCard title={t('orders.lines.title')}>
        <ol className="flex flex-col gap-2">
          {draft.lines.map((line, index) => (
            <li key={line.key} className="flex justify-between gap-4 border-b pb-2 text-sm">
              <span>
                {index + 1}. {shown(line.name)}
              </span>
              <span className="text-muted-foreground">{shown(line.quantity)}</span>
            </li>
          ))}
        </ol>
      </SectionCard>

      <SectionCard title={t('orders.packages.title')}>
        {nodes.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t('orders.packages.empty')}</p>
        ) : (
          <ol className="flex flex-col gap-2">
            {nodes.map((node, index) => (
              <li
                key={node.draft.key}
                style={{ marginLeft: `${node.depth * 1.5}rem` }}
                className="flex justify-between gap-4 border-b pb-2 text-sm"
              >
                <span>
                  {shown(node.draft.reference) === dash
                    ? t('orders.packages.position', { position: index + 1 })
                    : node.draft.reference}
                </span>
                <span className="text-muted-foreground">
                  {t('orders.lineCount', { count: node.draft.lines.length })}
                </span>
              </li>
            ))}
          </ol>
        )}
      </SectionCard>

      <SectionCard title={t('orders.services.title')}>
        <ol className="flex flex-col gap-2">
          {draft.services.map((service, index) => (
            <li key={service.key} className="flex justify-between gap-4 border-b pb-2 text-sm">
              <span>
                {index + 1}. {shown(service.serviceNumber)}
              </span>
              <span className="text-muted-foreground">{shown(service.requestedDate)}</span>
            </li>
          ))}
        </ol>
      </SectionCard>
    </div>
  )
}
