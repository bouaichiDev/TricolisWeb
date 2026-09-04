import { formatDate } from '@/shared/utils/format'

import type { OrderService } from '../../types/orderDetail'
import type { GridField } from './FieldGrid'

const show = (value: number | string | null | undefined): string | null =>
  value === null || value === undefined ? null : String(value)

/**
 * Les quinze champs d'un service, à plat.
 *
 * La ressource les groupe en `operational` / `billing` / `providerCost` — trois
 * angles sur la même prestation. Le panneau les présente d'une traite, dans
 * l'ordre où on les lit : identité, créneau, mesures, montants.
 */
export function serviceFields(service: OrderService | null): GridField[] {
  if (service === null) return []

  return [
    { labelKey: 'orders.fields.serviceNumber', value: service.serviceNumber },
    { labelKey: 'orders.fields.sequence', value: service.sequence },
    {
      labelKey: 'orders.fields.requestedDate',
      value: formatDate(service.operational.requestedDate),
    },
    {
      labelKey: 'orders.fields.requestedFrom',
      value: formatDate(service.operational.requestedFrom),
    },
    { labelKey: 'orders.fields.requestedTo', value: formatDate(service.operational.requestedTo) },
    {
      labelKey: 'orders.fields.quantity',
      value: `${show(service.operational.quantity) ?? ''} ${service.operational.unit ?? ''}`.trim(),
    },
    {
      labelKey: 'orders.fields.requiredTimeMinutes',
      value: show(service.operational.requiredTimeMinutes),
    },
    {
      labelKey: 'orders.fields.remainingTimeMinutes',
      value: show(service.operational.remainingTimeMinutes),
    },
    { labelKey: 'orders.fields.weight', value: show(service.operational.weight) },
    { labelKey: 'orders.fields.volume', value: show(service.operational.volume) },
    { labelKey: 'orders.fields.packageCount', value: show(service.operational.packageCount) },
    { labelKey: 'orders.fields.customerUnitPrice', value: show(service.billing.customerUnitPrice) },
    {
      labelKey: 'orders.fields.customerTotalPrice',
      value: show(service.billing.customerTotalPrice),
    },
    {
      labelKey: 'orders.fields.providerUnitCost',
      value: show(service.providerCost.providerUnitCost),
    },
    {
      labelKey: 'orders.fields.providerTotalCost',
      value: show(service.providerCost.providerTotalCost),
    },
  ]
}
