import type { ClaimDetail, ClaimListItem } from '@/modules/claims/types'
import type { OrderDetail, OrderDocument } from '@/modules/orders/types'
import type { ProofOfDelivery } from '@/modules/proofs/types'

const open = { code: 'open', label: 'Ouverte', icon: null }

export function makeClaim(overrides: Partial<ClaimListItem> = {}): ClaimListItem {
  return {
    id: '01CLAIM',
    title: 'Carton écrasé',
    claimType: 'damage',
    status: open,
    isClosed: false,
    order: { id: '01ORDER', reference: 'IKEA-2026-000001', customerReference: 'IKEA-4587' },
    orderService: { id: '01SVC1', serviceNumber: 'S1', name: 'Livraison' },
    createdAt: '2026-09-29T10:00:00+02:00',
    closedAt: null,
    ...overrides,
  }
}

export function makeClaimDetail(overrides: Partial<ClaimDetail> = {}): ClaimDetail {
  return { ...makeClaim(), description: 'Deux cartons ouverts.', actions: { canEdit: true, canAttach: true }, ...overrides }
}

export function makeDocument(overrides: Partial<OrderDocument> = {}): OrderDocument {
  return {
    id: '01DOC',
    fileName: 'bl-ikea.pdf',
    documentType: 'delivery_note',
    isDeliveryNote: true,
    referenceNumber: 'BL-IKEA-2026-000001-S1',
    mimeType: 'application/pdf',
    size: 2048,
    createdAt: '2026-09-29T10:00:00+02:00',
    ...overrides,
  }
}

export function makeProof(overrides: Partial<ProofOfDelivery> = {}): ProofOfDelivery {
  return {
    id: '01POD',
    orderId: '01ORDER',
    orderServiceId: '01SVC1',
    service: { id: '01SVC1', serviceNumber: 'S1', name: 'Livraison' },
    recipientName: 'Anna Berg',
    remark: 'Quai 3',
    deliveredAt: '2026-09-29T11:00:00+02:00',
    signature: { id: '01SIG', fileName: 'signature.png', mimeType: 'image/png', size: 100 },
    photo: null,
    ...overrides,
  }
}

export function makeOrderDetail(overrides: Partial<OrderDetail> = {}): OrderDetail {
  return {
    id: '01ORDER', reference: 'IKEA-2026-000001', customerReference: 'IKEA-4587', externalReference: null, orderDate: null,
    status: { code: 'planned', label: 'Planifiée', icon: null }, source: null, packageCount: 0, weight: null, createdAt: null,
    workerRemark: null, volume: null, updatedAt: null, lines: [], packages: [],
    services: [{
      id: '01SVC1', serviceNumber: 'S1', sequence: 1, service: { id: '01S', code: 'DELIVERY', name: 'Livraison' },
      requestedDate: '2026-10-01', requestedFrom: null, requestedTo: null, instructions: null,
      status: { code: 'completed', label: 'Effectué', icon: null }, address: null, contacts: [], packages: [],
    }],
    actions: { canView: true, canEdit: false, canDuplicate: false, canCancel: false, canTrack: false },
    ...overrides,
  }
}
