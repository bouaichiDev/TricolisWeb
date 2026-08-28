import type { SettleableService, SettlementLinePayload } from '../types/settlement'

/**
 * Les lignes d'un décompte, à partir des prestations retenues.
 *
 * **Le coût vient de la commande, pas du prix client.** `providerUnitCost` est
 * ce qui a été convenu avec le fournisseur ; reprendre `customerUnitPrice` lui
 * reverserait la marge. Les deux se ressemblent à l'écran, et c'est exactement
 * pourquoi la règle est ici plutôt que dans une saisie.
 *
 * Le décompte ne porte pas de numéro de ligne — le diagramme n'en a pas : les
 * lignes se lisent dans l'ordre où elles ont été retenues.
 */
export function linesFromServices(services: SettleableService[]): SettlementLinePayload[] {
  return services.map((service) => ({
    orderServiceId: service.id,
    description: describe(service),
    quantity: service.quantity,
    unitCost: service.providerUnitCost,
  }))
}

/**
 * Un total indicatif, avant enregistrement.
 *
 * Le serveur recalcule : ce total ne part pas. Il sert à voir où l'on en est
 * pendant qu'on compose.
 */
export function previewTotal(services: SettleableService[]): number {
  return services.reduce((sum, service) => sum + service.quantity * service.providerUnitCost, 0)
}

/**
 * De quoi reconnaître la prestation sur le décompte.
 *
 * Le fournisseur doit s'y retrouver : le service, et la commande qu'il a
 * servie. Le libellé est obligatoire côté serveur et borné à 255 caractères.
 */
function describe(service: SettleableService): string {
  const label = [service.serviceName ?? service.serviceCode ?? service.serviceNumber, service.orderNumber]
    .filter(Boolean)
    .join(' · ')

  return label.slice(0, 255)
}
