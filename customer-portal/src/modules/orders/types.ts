/** Capacités calculées par le backend : le portail n'en déduit aucune lui-même. */
export interface OrderActions {
  canView: boolean
  canEdit: boolean
  canDuplicate: boolean
  canCancel: boolean
  canTrack: boolean
}

export interface StatusView {
  code: string | null
  label: string | null
  icon: string | null
  /** Couleur réglée dans le référentiel (`#RRGGBB`), absente sinon. */
  color?: string | null
}

export interface OrderListItem {
  id: string
  reference: string
  customerReference: string | null
  externalReference: string | null
  orderDate: string | null
  firstRequestedDate: string | null
  status: StatusView
  source: string | null
  servicesCount: number
  packageCount: number
  weight: string | null
  createdAt: string | null
  actions: OrderActions
}

export interface OrderLine {
  id: string
  catalogItemId: string | null
  articleCode: string | null
  name: string | null
  description: string | null
  quantity: string
  weight: string | null
  volume: string | null
  deliveredQuantity: string | null
}

export interface OrderPackage {
  id: string
  parentPackageId: string | null
  packageType: { id: string; name: string } | null
  groupingType: { id: string; name: string } | null
  barcode: string | null
  reference: string | null
  description: string | null
  quantity: string | null
  weight: string | null
  volume: string | null
  status: StatusView
  lines: { orderLineId: string; quantity: string }[]
}

export interface ServiceContact {
  contactRole: string | null
  isPrimary: boolean
  firstName: string | null
  lastName: string | null
  phone: string | null
  mobile: string | null
  email: string | null
}

export interface OrderServiceView {
  id: string
  serviceNumber: string
  sequence: number
  service: { id: string; code: string; name: string } | null
  requestedDate: string | null
  requestedFrom: string | null
  requestedTo: string | null
  instructions: string | null
  status: StatusView
  address: {
    id: string
    name: string | null
    addressLine1: string | null
    addressLine2: string | null
    postalCode: string | null
    city: string | null
    country: string | null
    instructions: string | null
  } | null
  contacts: ServiceContact[]
  packages: { packageId: string; quantity: string }[]
}

export interface OrderDetail extends Omit<OrderListItem, 'firstRequestedDate' | 'servicesCount'> {
  workerRemark: string | null
  volume: string | null
  updatedAt: string | null
  lines: OrderLine[]
  packages: OrderPackage[]
  services: OrderServiceView[]
}

export interface OrderDocument {
  id: string
  fileName: string
  documentType: string
  /** Bon de livraison généré par le transporteur. */
  isDeliveryNote?: boolean
  referenceNumber: string | null
  mimeType: string
  size: number
  createdAt: string | null
}

export interface HistoryEntry {
  id: string
  type: string
  label: string
  at: string | null
  actor: string
  status: { from: string | null; to: string | null } | null
}

export interface Option {
  id: string
  name: string
}

export interface FormOptions {
  catalogEnabled: boolean
  services: { id: string; code: string; name: string; requiresContact: boolean }[]
  packageTypes: Option[]
  groupingTypes: Option[]
  addresses: { id: string; name: string | null; addressLine1: string | null; postalCode: string | null; city: string | null; country: string | null }[]
  contactRoles: { value: string; label: string }[]
  statuses: { code: string; label: string }[]
}

export interface CatalogItem {
  id: string
  articleCode: string
  name: string
  description: string | null
  weight: string | null
  volume: string | null
}

export interface OrderFilters {
  search?: string
  status?: string
  dateFrom?: string
  dateTo?: string
  service?: string
  sort?: string
  direction?: 'asc' | 'desc'
  page?: number
  perPage?: number
}
