import type { OrderDraft } from './orderDraft'
import { orderedPackages } from './packageOrder'
import type {
  CreateOrderPayload,
  OrderPackagePayload,
  OrderServicePayload,
} from '../types/orderPayload'

const number = (value: string): number => {
  const parsed = Number(value.trim())

  return Number.isFinite(parsed) ? parsed : 0
}

const optional = (value: string): number | undefined => {
  const trimmed = value.trim()

  return trimmed === '' ? undefined : number(trimmed)
}

const blank = (value: string): string | null => (value.trim() === '' ? null : value.trim())

/**
 * Charge utile accompagnée des clés du brouillon, dans l'ordre d'envoi.
 *
 * Le serveur répond à un 422 avec des chemins indexés — `packages.2.lines.0` —
 * qui portent la position **dans le tableau envoyé**. Les colis étant réordonnés
 * à la sérialisation, ces positions ne correspondent pas à l'ordre de saisie :
 * ces tableaux de clés permettent de remonter au bon élément du formulaire.
 */
export interface SerializedOrder {
  payload: CreateOrderPayload
  lineKeys: string[]
  packageKeys: string[]
  serviceKeys: string[]
}

/**
 * Traduit l'état du formulaire en charge utile de `POST /orders`.
 *
 * **C'est le seul endroit où la position d'une ligne est calculée.** Le backend
 * n'accepte pas de `lines[].key` : `CreateOrderLines` indexe son résultat par
 * `(string) $index`, donc `packages[].lines[].lineKey` doit porter la position
 * dans le tableau envoyé. Le formulaire, lui, ne manipule que des clés stables.
 *
 * Une ligne ou un colis référencé puis retiré est ignoré : envoyer une position
 * inexistante produirait un 422 sur un chemin que l'utilisateur ne peut plus
 * corriger.
 */
export function serializeOrderWithKeys(draft: OrderDraft): SerializedOrder {
  const linePosition = new Map<string, number>()
  draft.lines.forEach((line, index) => linePosition.set(line.key, index))

  const packages = orderedPackages(draft.packages)
  const packageKeys = new Set(packages.map((item) => item.key))

  const payload: CreateOrderPayload = {
    customerId: draft.customerId,
    agencyId: draft.agencyId,
    depotId: blank(draft.depotId),
    externalReference: blank(draft.externalReference),
    customerReference: blank(draft.customerReference),
    orderType: blank(draft.orderType),
    groupCode: blank(draft.groupCode),
    orderDate: draft.orderDate,
    currencyCode: draft.currencyCode.trim().toUpperCase(),
    internalRemark: blank(draft.internalRemark),
    workerRemark: blank(draft.workerRemark),

    lines: draft.lines.map((line) => ({
      catalogItemId: line.catalogItemId,
      // `name` n'est requis qu'en l'absence d'article catalogue : le backend
      // recopie alors le libellé de l'article.
      name: line.catalogItemId === null ? line.name.trim() : blank(line.name),
      articleCode: blank(line.articleCode),
      barcode: blank(line.barcode),
      description: blank(line.description),
      quantity: number(line.quantity),
      weight: optional(line.weight),
      volume: optional(line.volume),
    })),

    packages: packages.map(
      (item): OrderPackagePayload => ({
        key: item.key,
        parentKey: item.parentKey,
        packageTypeId: item.packageTypeId,
        groupingTypeId: item.groupingTypeId,
        barcode: blank(item.barcode),
        reference: blank(item.reference),
        description: blank(item.description),
        quantity: optional(item.quantity),
        weight: optional(item.weight),
        volume: optional(item.volume),
        lines: item.lines
          .filter((link) => linePosition.has(link.lineKey))
          .map((link) => ({
            lineKey: String(linePosition.get(link.lineKey)),
            quantity: number(link.quantity),
          })),
      }),
    ),

    services: draft.services.map(
      (service): OrderServicePayload => ({
        serviceId: service.serviceId,
        addressId: service.addressId,
        serviceNumber: service.serviceNumber.trim(),
        sequence: number(service.sequence),
        requestedDate: service.requestedDate,
        requestedFrom: blank(service.requestedFrom),
        requestedTo: blank(service.requestedTo),
        quantity: number(service.quantity),
        unit: service.unit.trim(),
        requiredTimeMinutes: number(service.requiredTimeMinutes),
        remainingTimeMinutes: number(service.remainingTimeMinutes),
        weight: number(service.weight),
        volume: number(service.volume),
        packageCount: number(service.packageCount),
        customerUnitPrice: number(service.customerUnitPrice),
        customerTotalPrice: number(service.customerTotalPrice),
        providerUnitCost: number(service.providerUnitCost),
        providerTotalCost: number(service.providerTotalCost),
        instructions: blank(service.instructions),
        status: service.status,
        contacts: service.contacts.map((contact) => ({
          contactId: contact.contactId,
          contactRole: contact.contactRole,
          isPrimary: contact.isPrimary,
          firstName: blank(contact.firstName),
          lastName: blank(contact.lastName),
          phone: blank(contact.phone),
          mobile: blank(contact.mobile),
          email: blank(contact.email),
        })),
        packages: service.packages
          .filter((link) => packageKeys.has(link.packageKey))
          .map((link) => ({
            packageKey: link.packageKey,
            quantity: optional(link.quantity),
            handlingInstructions: blank(link.handlingInstructions),
          })),
      }),
    ),
  }

  return {
    payload,
    lineKeys: draft.lines.map((line) => line.key),
    packageKeys: packages.map((item) => item.key),
    serviceKeys: draft.services.map((service) => service.key),
  }
}

export function serializeOrder(draft: OrderDraft): CreateOrderPayload {
  return serializeOrderWithKeys(draft).payload
}
