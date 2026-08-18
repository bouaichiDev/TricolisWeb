import { useTranslation } from 'react-i18next'

import { addressLabel } from '../../hooks/useServiceScope'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { DetailField } from '@/shared/components/layout/DetailField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { formatDate } from '@/shared/utils/format'

import type { OrderPackage, OrderService } from '../../types/orderDetail'

const show = (value: number | string | null | undefined): string | undefined =>
  value === null || value === undefined ? undefined : String(value)

interface OrderServicesTabProps {
  services: OrderService[]
  packages: OrderPackage[]
}

/**
 * Services de la commande, dans l'ordre de passage.
 *
 * Chaque service porte son adresse, son créneau, ses contacts et ses colis :
 * il n'y a pas d'onglet « Arrêts », parce qu'il n'y a pas d'entité `OrderStop`.
 *
 * Les contacts affichés sont les instantanés enregistrés avec la commande, pas
 * l'état actuel du contact partagé.
 */
export function OrderServicesTab({ services, packages }: OrderServicesTabProps) {
  const { t } = useTranslation()
  const packageLabel = new Map(
    packages.map((item) => [item.id, item.reference ?? item.barcode ?? item.id]),
  )

  if (services.length === 0) {
    return (
      <SectionCard title={t('orders.services.title')}>
        <EmptyState title={t('orders.services.title')} />
      </SectionCard>
    )
  }

  const ordered = [...services].sort((a, b) => a.sequence - b.sequence)

  return (
    <div className="flex flex-col gap-6">
      {ordered.map((service) => (
        <SectionCard
          key={service.id}
          title={`${service.sequence}. ${service.service?.name ?? service.serviceNumber}`}
          description={service.address ? addressLabel(service.address) : undefined}
          actions={<StatusBadge status={service.status} />}
        >
          <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <DetailField label={t('orders.fields.serviceNumber')}>
              {service.serviceNumber}
            </DetailField>
            <DetailField label={t('orders.fields.requestedDate')}>
              {formatDate(service.operational.requestedDate)}
            </DetailField>
            <DetailField label={t('orders.fields.quantity')}>
              {`${show(service.operational.quantity) ?? ''} ${service.operational.unit ?? ''}`.trim()}
            </DetailField>
            <DetailField label={t('orders.fields.requiredTimeMinutes')}>
              {show(service.operational.requiredTimeMinutes)}
            </DetailField>
            <DetailField label={t('orders.fields.weight')}>
              {show(service.operational.weight)}
            </DetailField>
            <DetailField label={t('orders.fields.volume')}>
              {show(service.operational.volume)}
            </DetailField>
            <DetailField label={t('orders.fields.customerTotalPrice')}>
              {show(service.billing.customerTotalPrice)}
            </DetailField>
            <DetailField label={t('orders.fields.providerTotalCost')}>
              {show(service.providerCost.providerTotalCost)}
            </DetailField>
            <DetailField label={t('orders.fields.packageCount')}>
              {show(service.operational.packageCount)}
            </DetailField>
          </dl>

          {service.operational.instructions !== null ? (
            <p className="mt-4 text-sm text-muted-foreground">
              {service.operational.instructions}
            </p>
          ) : null}

          {service.contacts && service.contacts.length > 0 ? (
            <div className="mt-4 border-t pt-4">
              <p className="mb-2 text-sm font-medium">{t('orders.services.contacts')}</p>
              <ul className="flex flex-col gap-1 text-sm">
                {service.contacts.map((contact) => (
                  <li key={contact.id} className="flex flex-wrap justify-between gap-2">
                    <span>
                      {`${contact.firstName ?? ''} ${contact.lastName ?? ''}`.trim() || '—'}
                      {contact.isPrimary ? ` · ${t('orders.services.isPrimary')}` : ''}
                    </span>
                    <span className="text-muted-foreground">
                      {contact.mobile ?? contact.phone ?? contact.email ?? '—'}
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}

          {service.packages && service.packages.length > 0 ? (
            <div className="mt-4 border-t pt-4">
              <p className="mb-2 text-sm font-medium">{t('orders.services.packages')}</p>
              <ul className="flex flex-col gap-1 text-sm">
                {service.packages.map((link) => (
                  <li key={link.id} className="flex flex-wrap justify-between gap-2">
                    <span>{packageLabel.get(link.packageId) ?? link.packageId}</span>
                    <span className="text-muted-foreground">{show(link.quantity) ?? '—'}</span>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
        </SectionCard>
      ))}
    </div>
  )
}
