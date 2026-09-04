/**
 * État du formulaire de création d'une commande.
 *
 * Chaque élément porte une **clé stable** produite par `crypto.randomUUID()`.
 * Elle sert d'identité en mémoire : clé de rendu React, cible des affectations
 * ligne ↔ colis, désignation d'un parent. Retirer un élément ne décale donc
 * rien, contrairement à un index de tableau.
 *
 * La conversion vers le contrat de l'API se fait dans `serializeOrder`, et
 * nulle part ailleurs : c'est le seul endroit où la position d'une ligne est
 * calculée, parce que c'est le seul endroit où le tableau est définitif.
 */
export interface LineDraft {
  key: string
  catalogItemId: string | null
  articleCode: string
  barcode: string
  externalReference: string
  name: string
  description: string
  quantity: string
  weight: string
  volume: string
  length: string
  width: string
  height: string
  purchasePrice: string
  sellingPrice: string
}

export interface PackageLineDraft {
  /** Clé de la ligne affectée, jamais sa position. */
  lineKey: string
  quantity: string
}

export interface PackageDraft {
  key: string
  parentKey: string | null
  packageTypeId: string | null
  groupingTypeId: string | null
  barcode: string
  reference: string
  description: string
  quantity: string
  weight: string
  volume: string
  lines: PackageLineDraft[]
}

export interface ServiceContactDraft {
  key: string
  contactId: string | null
  contactRole: string
  isPrimary: boolean
  firstName: string
  lastName: string
  phone: string
  mobile: string
  email: string
}

export interface ServicePackageDraft {
  packageKey: string
  quantity: string
  handlingInstructions: string
}

/**
 * Un service de commande : l'unité opérationnelle adressée.
 *
 * Les quatre montants sont saisis, jamais posés à zéro d'office — le §29
 * l'interdit, et un zéro implicite serait une donnée métier fabriquée.
 */
export interface ServiceDraft {
  key: string
  serviceId: string
  addressId: string
  serviceNumber: string
  sequence: string
  requestedDate: string
  requestedFrom: string
  requestedTo: string
  quantity: string
  unit: string
  requiredTimeMinutes: string
  remainingTimeMinutes: string
  weight: string
  volume: string
  packageCount: string
  customerUnitPrice: string
  customerTotalPrice: string
  providerUnitCost: string
  providerTotalCost: string
  instructions: string
  status: string
  contacts: ServiceContactDraft[]
  packages: ServicePackageDraft[]
}

export interface OrderDraft {
  customerId: string
  agencyId: string
  depotId: string
  externalReference: string
  customerReference: string
  orderType: string
  groupCode: string
  orderDate: string
  currencyCode: string
  internalRemark: string
  workerRemark: string
  lines: LineDraft[]
  packages: PackageDraft[]
  services: ServiceDraft[]
}

export {
  newKey,
  emptyLine,
  emptyPackage,
  emptyContact,
  emptyService,
  emptyDraft,
} from './orderDraftFactories'
