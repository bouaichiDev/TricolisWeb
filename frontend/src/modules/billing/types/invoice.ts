import type { ListParams } from '@/shared/api/types'

/**
 * Une facture client.
 *
 * Les montants arrivent en chaînes : le serveur les rend tels quels depuis des
 * décimaux, et les convertir en nombres ici les ferait dériver à l'affichage
 * comme à la relecture.
 */
export interface Invoice {
  id: string
  organizationId: string
  customerId: string
  customerName?: string
  invoiceNumber: string
  invoiceDate: string | null
  periodFrom: string | null
  periodTo: string | null
  currencyCode: string
  subtotal: string
  taxTotal: string
  total: string
  externalReference: string | null
  status: string
  createdAt: string | null
  lineCount?: number
}

export interface InvoiceLineAddressSnapshot {
  addressCode: string | null
  name: string | null
  addressLine1: string | null
  addressLine2: string | null
  postalCode: string | null
  city: string | null
  country: string | null
}

export interface InvoiceLine {
  id: string
  invoiceId: string
  orderServiceId: string | null
  orderId: string | null
  lineNumber: number
  serviceCode: string | null
  description: string
  customerOrderReference: string | null
  quantity: string
  unitPrice: string
  discountRate: string
  taxRate: string
  totalExcludingTax: string
  totalIncludingTax: string
  serviceCompletedAt: string | null
  status: string
  addressSnapshot?: InvoiceLineAddressSnapshot | null
}

export interface InvoiceDetail extends Invoice {
  remark: string | null
  customer?: { id: string; code: string; name: string; status: string | null }
  lines: InvoiceLine[]
}

/** Un envoi déclenché par la clôture. */
export interface ExportJob {
  id: string
  customerId: string
  configurationId: string
  entityType: string | null
  entityId: string | null
  fileName: string | null
  hasFile: boolean
  status: string
  attemptCount: number
  generatedAt: string | null
  sentAt: string | null
  errorMessage: string | null
  configuration?: { id: string; name: string; transport: string; format: string }
}

/** Ce que la clôture a produit : la facture figée, et ce qui part. */
export interface InvoiceClosureResult {
  invoice: InvoiceDetail
  exportJobs: ExportJob[]
}

export interface InvoiceFilters extends ListParams {
  /** Le referentiel gouverne les codes : `draft` et `closed` pour une facture. */
  status?: string
  customerId?: string
  invoiceNumber?: string
  invoiceDateFrom?: string
  invoiceDateTo?: string
  periodFrom?: string
  periodTo?: string
  currencyCode?: string
}

/** Une ligne telle qu'on la soumet, à la création comme à l'ajout. */
export interface InvoiceLinePayload {
  orderServiceId?: string | null
  orderId?: string | null
  lineNumber: number
  serviceCode?: string | null
  description: string
  customerOrderReference?: string | null
  quantity: number
  unitPrice: number
  discountRate?: number
  taxRate?: number
  serviceCompletedAt?: string | null
  status: string
  /**
   * Assumer le prix soumis, faute de bareme.
   *
   * Sans lui, le serveur refuse une prestation sans tarif plutot que de la
   * facturer au hasard. Il ne contourne rien quand un bareme existe.
   */
  priceOverride?: boolean
}

export interface InvoicePayload {
  customerId: string
  /** Omis : le serveur l'attribue, et la reference externe le reprend. */
  invoiceNumber?: string
  invoiceDate: string
  periodFrom?: string | null
  periodTo?: string | null
  currencyCode: string
  externalReference?: string | null
  remark?: string | null
  status: string
  lines: InvoiceLinePayload[]
}

/**
 * Une prestation que le serveur juge encore facturable.
 *
 * L'éligibilité n'est pas recalculée ici : l'écran affiche ce que le serveur
 * propose, sans quoi les deux règles finiraient par diverger.
 */
export interface BillableService {
  id: string
  serviceNumber: string
  orderId: string
  orderNumber?: string
  customerReference?: string | null
  /** Celle de la commande : une facture n'en porte qu'une. */
  currencyCode?: string | null
  serviceCode?: string | null
  serviceName?: string | null
  requestedDate: string | null
  quantity: number
  unit: string | null
  customerUnitPrice: number
  customerTotalPrice: number
  weight: number
  volume: number
  packageCount: number
  status: string
  address?: {
    id: string
    code: string | null
    name: string | null
    addressLine1: string | null
    postalCode: string | null
    city: string | null
    country: string | null
  } | null
}

/**
 * Les filtres du sélecteur, colonne par colonne.
 *
 * Ils partent au serveur : une liste paginée filtrée dans le navigateur ne
 * porterait que sur la page affichée.
 */
export interface BillableServiceFilters extends ListParams {
  periodFrom?: string
  periodTo?: string
  /** Plusieurs valeurs, cumulees en « ou » par le serveur. */
  service?: string[]
  order?: string
  reference?: string
  address?: string
  quantityMin?: number
  quantityMax?: number
  priceMin?: number
  priceMax?: number
}

/**
 * Ce qu'un recalcul changerait, ligne par ligne.
 *
 * `newUnitPrice` nul dit qu'aucun tarif ne couvre plus la prestation : la ligne
 * gardera son prix, un echec de calcul ne devenant pas un montant.
 */
export interface RepricingChange {
  lineId: string
  lineNumber: number
  description: string
  currentUnitPrice: string
  newUnitPrice: string | null
  reason: string | null
  scope?: string | null
  formula?: string | null
}

/** Ce que la clôture ferait, avant de la déclencher. */
export interface InvoiceClosurePreview {
  closable: boolean
  lineCount: number
  destinations: {
    id: string
    name: string
    transport: string
    format: string
  }[]
}
