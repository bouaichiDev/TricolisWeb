import type { ListParams } from '@/shared/api/types'

/**
 * Un décompte fournisseur.
 *
 * C'est le miroir de la facture, côté sortant : ce qu'on doit à celui qui a
 * roulé. Les montants restent des chaînes, pour la même raison qu'en
 * facturation — un décimal passé en flottant dérive à la relecture.
 */
export interface ProviderSettlement {
  id: string
  organizationId: string
  providerId: string
  providerName?: string
  settlementNumber: string
  periodFrom: string | null
  periodTo: string | null
  subtotal: string
  taxTotal: string
  total: string
  status: string
  lineCount?: number
}

export interface ProviderSettlementLine {
  id: string
  settlementId: string
  orderServiceId: string | null
  description: string
  quantity: string
  unitCost: string
  totalCost: string
}

export interface ProviderSettlementDetail extends ProviderSettlement {
  provider?: { id: string; code: string; name: string }
  lines: ProviderSettlementLine[]
}

export interface SettlementFilters extends ListParams {
  status?: string
  periodFrom?: string
  periodTo?: string
}

export interface SettlementLinePayload {
  orderServiceId?: string | null
  description: string
  quantity: number
  unitCost: number
}

export interface SettlementPayload {
  providerId: string
  settlementNumber: string
  periodFrom?: string | null
  periodTo?: string | null
  taxTotal?: number
  status: string
  lines: SettlementLinePayload[]
}

/**
 * Une prestation qu'il reste à régler à ce fournisseur.
 *
 * `providerUnitCost` est ce qu'on doit, fixé à la commande. `customerUnitPrice`
 * est là pour se repérer, et **n'est pas un coût** : les confondre reviendrait
 * à reverser la marge au fournisseur.
 */
export interface SettleableService {
  id: string
  serviceNumber: string
  orderId: string
  orderNumber?: string
  customerReference?: string | null
  customerName?: string | null
  serviceCode?: string | null
  serviceName?: string | null
  requestedDate: string | null
  quantity: number
  unit: string | null
  customerUnitPrice: number
  providerUnitCost: number
  providerTotalCost: number
  weight: number
  volume: number
  packageCount: number
  status: string
  address?: {
    id: string
    code: string | null
    name: string | null
    postalCode: string | null
    city: string | null
  } | null
}

export interface SettleableServiceFilters extends ListParams {
  periodFrom?: string
  periodTo?: string
}
