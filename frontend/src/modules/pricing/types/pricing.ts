import type { ListParams } from '@/shared/api/types'

/**
 * Une variable du catalogue de la plateforme.
 *
 * Le catalogue n'est plus une constante du frontend : c'est le superadmin qui
 * decide quelles variables existent et d'ou elles sortent. Un organisme les
 * emploie, il ne les invente pas.
 *
 * `kind` separe ce qui se calcule de ce qui filtre : on multiplie un poids, on
 * ne multiplie pas un code postal.
 */
export interface PricingVariable {
  id: string
  code: string
  label: string
  description: string | null
  kind: 'numeric' | 'dimension'
  sourceKey: string
  sourceTable: string | null
  sourceColumn: string | null
  unit: string | null
  position: number
  isActive: boolean
}

/** Une source lisible, telle que le registre du serveur la declare. */
export interface PricingVariableSource {
  key: string
  table: string
  column: string
  kind: 'numeric' | 'dimension'
  label: string
}

export interface PricingVariablePayload {
  code: string
  label: string
  description?: string | null
  sourceKey: string
  unit?: string | null
  position?: number
  isActive?: boolean
}

export const CONDITION_OPERATORS = [
  '=',
  '!=',
  '<',
  '<=',
  '>',
  '>=',
  'between',
  'in',
  'starts_with',
] as const

export const MATCH_MODES = ['numeric', 'prefix', 'exact'] as const

export interface PriceRuleCondition {
  id: string
  priceRuleId: string
  variable: string
  operator: string
  valueFrom: string | null
  valueTo: string | null
}

export interface PriceRule {
  id: string
  priceListId: string
  serviceId: string | null
  serviceCode?: string | null
  serviceName?: string | null
  code: string
  name: string
  formula: string
  priority: number
  isActive: boolean
  /** Une matrice la désigne : elle ne vaut alors que dans ses zones. */
  matrixDriven?: boolean
  conditions?: PriceRuleCondition[]
}

export interface PriceMatrixRow {
  id: string
  priceRuleId: string
  priceRuleCode?: string | null
  formula?: string | null
  label: string
  matchMode: string
  rangeFrom: string
  rangeTo: string | null
  priority: number
}

export interface PriceMatrix {
  id: string
  priceListId: string
  serviceId: string | null
  serviceCode?: string | null
  code: string
  name: string
  dimension: string
  isActive: boolean
  rows?: PriceMatrixRow[]
}

export interface PriceList {
  id: string
  organizationId: string
  code: string
  name: string
  /** `global` s'applique à tous ; `customer` prime pour les clients liés. */
  scope: string
  validFrom: string | null
  validTo: string | null
  isActive: boolean
  ruleCount?: number
  matrixCount?: number
  customers?: { id: string; code: string; name: string }[]
  rules?: PriceRule[]
  matrices?: PriceMatrix[]
}

export interface PriceListFilters extends ListParams {
  scope?: string
  customerId?: string
}

export interface PriceListPayload {
  code: string
  name: string
  scope: string
  validFrom?: string | null
  validTo?: string | null
  isActive?: boolean
  customerIds?: string[]
}

export interface PriceRulePayload {
  code: string
  name: string
  formula: string
  serviceId?: string | null
  priority?: number
  isActive?: boolean
  conditions?: { variable: string; operator: string; valueFrom: string; valueTo?: string | null }[]
}

export interface PriceMatrixPayload {
  code: string
  name: string
  serviceId?: string | null
  dimension?: string
  isActive?: boolean
  rows: {
    label: string
    priceRuleId: string
    matchMode?: string
    rangeFrom: string
    rangeTo?: string | null
    priority?: number
  }[]
}

/**
 * Ce que le serveur répond à une vérification de formule.
 *
 * `valid` porte sur la formule ; `result.error` sur l'essai. Une formule juste
 * peut échouer à l'essai — une division par zéro, par exemple — sans être
 * fautive pour autant.
 */
export interface FormulaCheck {
  valid: boolean
  error: string | null
  variables: string[]
  unknownVariables: string[]
  result: { amount: string | null; error: string | null } | null
}

/** Une prestation à facturer, avec le tarif que le barème lui donnerait. */
export interface PrebillingService {
  id: string
  serviceNumber: string
  orderId: string
  orderNumber: string | null
  customerReference: string | null
  customerId: string | null
  customerName: string | null
  serviceCode: string | null
  serviceName: string | null
  requestedDate: string | null
  currencyCode: string | null
  weight: number
  volume: number
  packageCount: number
  quantity: number
  postalCode: string | null
  city: string | null
  currentUnitPrice: number
  priced: boolean
  calculatedPrice: string | null
  reason: string | null
  scope: string | null
  formula: string | null
  priceRuleCode: string | null
  zone: string | null
}

export interface PrebillingFilters extends ListParams {
  customerId?: string
  periodFrom?: string
  periodTo?: string
}
