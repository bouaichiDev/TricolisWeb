import { Pencil, RefreshCw, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { Disclosure } from '@/shared/components/layout/Disclosure'
import { DetailField } from '@/shared/components/layout/DetailField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { formatDate } from '@/shared/utils/format'

import { addressLabel } from '../../hooks/useServiceScope'
import type { OrderService } from '../../types/orderDetail'
import { EntityHistory } from './EntityHistory'
import { SummaryRow } from './SummaryRow'

const show = (value: number | string | null | undefined): string | undefined =>
  value === null || value === undefined ? undefined : String(value)

interface OrderServiceCardViewProps {
  service: OrderService
  packageLabel: Map<string, string>
  editable: boolean
  onEdit: () => void
  onDelete: () => void
  onChangeStatus: () => void
}

/**
 * Un service tel qu'il est enregistré : adresse, créneau, montants, contacts.
 *
 * Six valeurs en clair — date, quantité, unité, poids, volume, colis — et le
 * reste sous le repli : montants, contacts, colis pris en charge.
 *
 * Les contacts affichés sont les instantanés pris à la création — modifier le
 * contact partagé plus tard ne réécrit pas ce que la commande a enregistré.
 *
 * Le statut se change par sa propre action : `order_services.change_status` est
 * une permission distincte de `order_services.update`.
 */
export function OrderServiceCardView({
  service,
  packageLabel,
  editable,
  onEdit,
  onDelete,
  onChangeStatus,
}: OrderServiceCardViewProps) {
  const { t } = useTranslation()

  return (
    <SectionCard
      title={`${service.sequence}. ${service.service?.name ?? service.serviceNumber}`}
      description={service.address ? addressLabel(service.address) : undefined}
      actions={
        <div className="flex items-center gap-1">
          <StatusBadge status={service.status} />

          <PermissionGuard permission="order_services.change_status">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={onChangeStatus}
              aria-label={t('orders.services.changeStatus')}
            >
              <RefreshCw className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>

          {editable ? (
            <>
              <PermissionGuard permission="order_services.update">
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={onEdit}
                  aria-label={t('orders.services.edit')}
                >
                  <Pencil className="size-4" aria-hidden />
                </Button>
              </PermissionGuard>

              <PermissionGuard permission="order_services.delete">
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={onDelete}
                  aria-label={t('orders.services.remove')}
                >
                  <Trash2 className="size-4" aria-hidden />
                </Button>
              </PermissionGuard>
            </>
          ) : null}
        </div>
      }
    >
      <SummaryRow
        items={[
          { labelKey: 'orders.fields.serviceNumber', value: service.serviceNumber },
          { labelKey: 'orders.fields.requestedDate', value: formatDate(service.operational.requestedDate) },
          {
            labelKey: 'orders.fields.quantity',
            value: `${show(service.operational.quantity) ?? ''} ${service.operational.unit ?? ''}`.trim(),
          },
          { labelKey: 'orders.fields.weight', value: show(service.operational.weight) },
          { labelKey: 'orders.fields.volume', value: show(service.operational.volume) },
          { labelKey: 'orders.fields.packageCount', value: show(service.operational.packageCount) },
        ]}
      />

      <div className="mt-2 border-t pt-2">
        <Disclosure>
          <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <DetailField label={t('orders.fields.requestedFrom')}>
              {formatDate(service.operational.requestedFrom)}
            </DetailField>
            <DetailField label={t('orders.fields.requestedTo')}>
              {formatDate(service.operational.requestedTo)}
            </DetailField>
            <DetailField label={t('orders.fields.requiredTimeMinutes')}>
              {show(service.operational.requiredTimeMinutes)}
            </DetailField>
            <DetailField label={t('orders.fields.remainingTimeMinutes')}>
              {show(service.operational.remainingTimeMinutes)}
            </DetailField>
            <DetailField label={t('orders.fields.customerUnitPrice')}>
              {show(service.billing.customerUnitPrice)}
            </DetailField>
            <DetailField label={t('orders.fields.customerTotalPrice')}>
              {show(service.billing.customerTotalPrice)}
            </DetailField>
            <DetailField label={t('orders.fields.providerUnitCost')}>
              {show(service.providerCost.providerUnitCost)}
            </DetailField>
            <DetailField label={t('orders.fields.providerTotalCost')}>
              {show(service.providerCost.providerTotalCost)}
            </DetailField>
          </dl>

          {service.operational.instructions !== null ? (
            <p className="mt-4 text-sm text-muted-foreground">{service.operational.instructions}</p>
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

        </Disclosure>
      </div>

      <div className="mt-4 border-t pt-4">
        <EntityHistory entityType="order_service" entityId={service.id} />
      </div>
    </SectionCard>
  )
}
