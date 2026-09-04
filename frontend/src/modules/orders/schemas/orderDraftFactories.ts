import type {
  LineDraft,
  OrderDraft,
  PackageDraft,
  ServiceContactDraft,
  ServiceDraft,
} from './orderDraft'

/**
 * Valeurs de départ d'un brouillon de commande.
 *
 * Une commande naît avec une ligne et un service, parce que le serveur exige
 * au moins l'un et l'autre. Elle naît sans colis, parce qu'ils sont facultatifs.
 *
 * Les types restent dans `orderDraft.ts` et n'y sont importés qu'en type : ce
 * fichier ne dépend donc d'aucun module à l'exécution.
 */
export function newKey(): string {
  return crypto.randomUUID()
}

export function emptyLine(): LineDraft {
  return {
    key: newKey(),
    catalogItemId: null,
    articleCode: '',
    barcode: '',
    externalReference: '',
    name: '',
    description: '',
    quantity: '1',
    weight: '',
    volume: '',
    length: '',
    width: '',
    height: '',
    purchasePrice: '',
    sellingPrice: '',
  }
}

export function emptyPackage(): PackageDraft {
  return {
    key: newKey(),
    parentKey: null,
    packageTypeId: null,
    groupingTypeId: null,
    barcode: '',
    reference: '',
    description: '',
    quantity: '1',
    weight: '',
    volume: '',
    lines: [],
  }
}

export function emptyContact(): ServiceContactDraft {
  return {
    key: newKey(),
    contactId: null,
    contactRole: 'delivery',
    isPrimary: false,
    firstName: '',
    lastName: '',
    phone: '',
    mobile: '',
    email: '',
  }
}

export function emptyService(sequence: number): ServiceDraft {
  return {
    key: newKey(),
    serviceId: '',
    addressId: '',
    serviceNumber: '',
    sequence: String(sequence),
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
    status: 'draft',
    contacts: [],
    packages: [],
  }
}

export function emptyDraft(): OrderDraft {
  return {
    customerId: '',
    agencyId: '',
    depotId: '',
    externalReference: '',
    customerReference: '',
    orderType: '',
    groupCode: '',
    orderDate: new Date().toISOString().slice(0, 10),
    // `CreateFullOrder` retient `MAD` quand rien n'est envoyé : le formulaire
    // propose la même valeur plutôt qu'une devise concurrente.
    currencyCode: 'MAD',
    internalRemark: '',
    workerRemark: '',
    lines: [emptyLine()],
    packages: [],
    services: [emptyService(1)],
  }
}
