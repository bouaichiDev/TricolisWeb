import type { OrderService } from '../../types/orderDetail'

export interface ServiceFieldSpec {
  name: string
  labelKey: string
  type: 'text' | 'number' | 'date'
  /** Envoyé en `number` plutôt qu'en chaîne. */
  numeric?: boolean
  /** Accepte l'absence de valeur — `nullable` côté serveur. */
  nullable?: boolean
}

/**
 * Champs modifiables d'un service, relevés sur `UpdateOrderServiceRequest`.
 *
 * `status` n'y figure pas : il a sa propre route et sa propre permission,
 * `order_services.change_status` — avancer une prestation dans son cycle n'est
 * pas la même responsabilité que corriger son adresse.
 */
export const SERVICE_FIELDS: ServiceFieldSpec[] = [
  { name: 'serviceNumber', labelKey: 'orders.fields.serviceNumber', type: 'text' },
  { name: 'sequence', labelKey: 'orders.fields.sequence', type: 'number', numeric: true },
  { name: 'requestedDate', labelKey: 'orders.fields.requestedDate', type: 'date' },
  {
    name: 'requestedFrom',
    labelKey: 'orders.fields.requestedFrom',
    type: 'date',
    nullable: true,
  },
  { name: 'requestedTo', labelKey: 'orders.fields.requestedTo', type: 'date', nullable: true },
  { name: 'quantity', labelKey: 'orders.fields.quantity', type: 'number', numeric: true },
  { name: 'unit', labelKey: 'orders.fields.unit', type: 'text' },
  {
    name: 'requiredTimeMinutes',
    labelKey: 'orders.fields.requiredTimeMinutes',
    type: 'number',
    numeric: true,
  },
  {
    name: 'remainingTimeMinutes',
    labelKey: 'orders.fields.remainingTimeMinutes',
    type: 'number',
    numeric: true,
  },
  { name: 'weight', labelKey: 'orders.fields.weight', type: 'number', numeric: true },
  { name: 'volume', labelKey: 'orders.fields.volume', type: 'number', numeric: true },
  {
    name: 'packageCount',
    labelKey: 'orders.fields.packageCount',
    type: 'number',
    numeric: true,
  },
]

/** Les quatre montants, isolés pour être présentés ensemble et expliqués. */
export const SERVICE_PRICE_FIELDS: ServiceFieldSpec[] = [
  {
    name: 'customerUnitPrice',
    labelKey: 'orders.fields.customerUnitPrice',
    type: 'number',
    numeric: true,
  },
  {
    name: 'customerTotalPrice',
    labelKey: 'orders.fields.customerTotalPrice',
    type: 'number',
    numeric: true,
  },
  {
    name: 'providerUnitCost',
    labelKey: 'orders.fields.providerUnitCost',
    type: 'number',
    numeric: true,
  },
  {
    name: 'providerTotalCost',
    labelKey: 'orders.fields.providerTotalCost',
    type: 'number',
    numeric: true,
  },
]

const show = (value: number | string | null | undefined): string =>
  value === null || value === undefined ? '' : String(value)

/**
 * Valeurs de départ du formulaire, à plat.
 *
 * La ressource groupe les champs en `operational` / `billing` / `providerCost` ;
 * la charge utile de modification, elle, est plate. La conversion se fait ici,
 * en un seul endroit.
 */
export function serviceFormValues(service: OrderService | null): Record<string, string> {
  if (service === null) {
    return {
      serviceNumber: '',
      sequence: '1',
      requestedDate: '',
      requestedFrom: '',
      requestedTo: '',
      quantity: '1',
      unit: '',
      requiredTimeMinutes: '0',
      remainingTimeMinutes: '0',
      weight: '0',
      volume: '0',
      packageCount: '0',
      customerUnitPrice: '',
      customerTotalPrice: '',
      providerUnitCost: '',
      providerTotalCost: '',
      instructions: '',
    }
  }

  return {
    serviceNumber: service.serviceNumber,
    sequence: String(service.sequence),
    requestedDate: (service.operational.requestedDate ?? '').slice(0, 10),
    requestedFrom: (service.operational.requestedFrom ?? '').slice(0, 10),
    requestedTo: (service.operational.requestedTo ?? '').slice(0, 10),
    quantity: show(service.operational.quantity),
    unit: service.operational.unit ?? '',
    requiredTimeMinutes: show(service.operational.requiredTimeMinutes),
    remainingTimeMinutes: show(service.operational.remainingTimeMinutes),
    weight: show(service.operational.weight),
    volume: show(service.operational.volume),
    packageCount: show(service.operational.packageCount),
    customerUnitPrice: show(service.billing.customerUnitPrice),
    customerTotalPrice: show(service.billing.customerTotalPrice),
    providerUnitCost: show(service.providerCost.providerUnitCost),
    providerTotalCost: show(service.providerCost.providerTotalCost),
    instructions: service.operational.instructions ?? '',
  }
}
