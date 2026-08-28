import type { BillableService, InvoiceLinePayload } from '../types/invoice'

/** Le statut d'une ligne facturable — le seul que le référentiel définisse. */
export const BILLABLE_LINE_STATUS = 'billable'

/**
 * Les lignes d'une facture, à partir des prestations retenues.
 *
 * **Aucun montant n'est décidé ici.** La quantité et le prix viennent de la
 * prestation, telle que le serveur l'a rendue ; les totaux, eux, sont
 * recalculés à l'enregistrement. Les additionner dans l'écran donnerait un
 * second calcul à maintenir, et deux calculs finissent toujours par différer.
 *
 * La numérotation suit l'ordre de sélection : c'est celui que le facturier a
 * en tête, et il doit se retrouver sur le document.
 */
export function linesFromServices(services: BillableService[]): InvoiceLinePayload[] {
  return services.map((service, index) => ({
    orderServiceId: service.id,
    orderId: service.orderId,
    lineNumber: index + 1,
    serviceCode: service.serviceCode ?? null,
    description: describe(service),
    customerOrderReference: service.customerReference ?? null,
    quantity: service.quantity,
    unitPrice: service.customerUnitPrice,
    status: BILLABLE_LINE_STATUS,
  }))
}

/**
 * Un total indicatif, avant enregistrement.
 *
 * Il n'est pas envoyé : le serveur recalcule. Il sert uniquement à ce que le
 * facturier voie où il en est pendant qu'il compose.
 */
export function previewTotal(services: BillableService[]): number {
  return services.reduce((sum, service) => sum + service.quantity * service.customerUnitPrice, 0)
}

/**
 * De quoi reconnaître la prestation sur le document.
 *
 * Le libellé est obligatoire côté serveur et limité à 255 caractères. Le nom du
 * service le porte quand il existe ; à défaut, son code, puis son numéro — un
 * client lit mal « 01K7… », mais c'est encore mieux qu'une ligne sans nom.
 */
function describe(service: BillableService): string {
  const label = service.serviceName ?? service.serviceCode ?? service.serviceNumber

  return label.slice(0, 255)
}
