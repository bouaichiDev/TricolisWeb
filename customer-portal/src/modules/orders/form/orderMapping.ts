import type { OrderDetail } from '../types'
import type { LineValues, OrderFormValues, OrderPayload, PackageValues, ServiceValues } from './orderSchema'

const blankToNull = (value: string): string | null => (value.trim() === '' ? null : value.trim())
const num = (value: string): number | null => (value.trim() === '' ? null : Number(value.replace(',', '.')))

export const newUid = (): string => Math.random().toString(36).slice(2, 10)

export const emptyLine = (): LineValues => ({ uid: newUid(), catalogItemId: '', articleLabel: '', name: '', quantity: '1', weight: '' })

export const emptyPackage = (): PackageValues => ({ uid: newUid(), packageTypeId: '', groupingTypeId: '', reference: '', weight: '', lines: [] })

export const emptyService = (): ServiceValues => ({
  serviceId: '',
  addressMode: 'book',
  addressId: '',
  recipient: { firstName: '', lastName: '', company: '', addressLine1: '', postalCode: '', city: '', country: 'CH', phone: '' },
  requestedDate: '',
  requestedFrom: '',
  requestedTo: '',
  instructions: '',
  contacts: [],
  packageUids: [],
})

export const emptyOrder = (): OrderFormValues => ({
  customerReference: '',
  externalReference: '',
  workerRemark: '',
  lines: [emptyLine()],
  packages: [],
  services: [emptyService()],
})

/** Formulaire → corps de requête. Les `uid` deviennent les clés attendues. */
export function toPayload(values: OrderFormValues): OrderPayload {
  const lineIndex = new Map(values.lines.map((line, index) => [line.uid, String(index)]))

  return {
    customerReference: blankToNull(values.customerReference),
    externalReference: blankToNull(values.externalReference),
    workerRemark: blankToNull(values.workerRemark),
    lines: values.lines.map((line) => ({
      catalogItemId: line.catalogItemId || null,
      name: blankToNull(line.name),
      quantity: num(line.quantity),
      weight: num(line.weight),
    })),
    packages: values.packages.map((pkg) => ({
      key: pkg.uid,
      packageTypeId: pkg.packageTypeId || null,
      groupingTypeId: pkg.groupingTypeId || null,
      reference: blankToNull(pkg.reference),
      weight: num(pkg.weight),
      lines: pkg.lines.map((line) => ({ lineKey: lineIndex.get(line.lineUid) ?? line.lineUid, quantity: num(line.quantity) })),
    })),
    services: values.services.map((service) => ({
      serviceId: service.serviceId,
      addressId: service.addressMode === 'book' ? service.addressId : null,
      address: service.addressMode === 'new' ? { ...service.recipient, country: service.recipient.country.toUpperCase() } : null,
      requestedDate: service.requestedDate,
      requestedFrom: blankToNull(service.requestedFrom),
      requestedTo: blankToNull(service.requestedTo),
      instructions: blankToNull(service.instructions),
      contacts: service.contacts.map((contact) => ({
        firstName: contact.firstName,
        lastName: blankToNull(contact.lastName),
        phone: blankToNull(contact.phone),
        email: blankToNull(contact.email),
      })),
      packages: service.packageUids.map((uid) => ({ packageKey: uid })),
    })),
  }
}

/** Détail d'une commande → formulaire de modification. */
export function fromDetail(order: OrderDetail): OrderFormValues {
  return {
    customerReference: order.customerReference ?? '',
    externalReference: order.externalReference ?? '',
    workerRemark: order.workerRemark ?? '',
    lines: order.lines.map((line) => ({
      uid: line.id,
      catalogItemId: line.catalogItemId ?? '',
      articleLabel: line.catalogItemId ? `${line.articleCode ?? ''} — ${line.name ?? ''}` : '',
      name: line.catalogItemId ? '' : (line.name ?? ''),
      quantity: String(Number(line.quantity)),
      weight: line.weight === null ? '' : String(Number(line.weight)),
    })),
    packages: order.packages.map((pkg) => ({
      uid: pkg.id,
      packageTypeId: pkg.packageType?.id ?? '',
      groupingTypeId: pkg.groupingType?.id ?? '',
      reference: pkg.reference ?? '',
      weight: pkg.weight === null ? '' : String(Number(pkg.weight)),
      lines: pkg.lines.map((line) => ({ lineUid: line.orderLineId, quantity: String(Number(line.quantity)) })),
    })),
    services: order.services.map((service) => ({
      ...emptyService(),
      serviceId: service.service?.id ?? '',
      addressId: service.address?.id ?? '',
      requestedDate: service.requestedDate ?? '',
      requestedFrom: service.requestedFrom ?? '',
      requestedTo: service.requestedTo ?? '',
      instructions: service.instructions ?? '',
      contacts: service.contacts.map((contact) => ({
        firstName: contact.firstName ?? '',
        lastName: contact.lastName ?? '',
        phone: contact.phone ?? '',
        email: contact.email ?? '',
      })),
      packageUids: service.packages.map((link) => link.packageId),
    })),
  }
}
