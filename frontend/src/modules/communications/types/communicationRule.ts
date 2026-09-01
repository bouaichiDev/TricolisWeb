import type { Template } from '@/modules/templates/types/template'
import type { CommunicationEventType, RecipientRole } from './communication'

/**
 * Unités de délai réellement acceptées par `StoreCommunicationRuleRequest`.
 *
 * La liste vient du serveur, jamais d'une invention : le §37 l'interdit, et une
 * unité de plus ferait échouer la création sans que l'écran sache pourquoi.
 */
export const DELAY_UNITS = ['minutes', 'hours', 'days'] as const

export type DelayUnit = (typeof DELAY_UNITS)[number]

/**
 * Opérateurs de `CommunicationRuleConditionEvaluator`.
 *
 * Huit, clos. Ni `any`, ni `not`, ni imbrication : le schéma est une
 * **conjonction plate**, et l'écran ne doit pas laisser composer ce que
 * l'évaluateur refusera.
 */
export const CONDITION_OPERATORS = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in'] as const

export type ConditionOperator = (typeof CONDITION_OPERATORS)[number]

/** Les opérateurs dont la valeur est une liste, pas un scalaire. */
export function operatorTakesList(operator: string): boolean {
  return operator === 'in' || operator === 'not_in'
}

export interface RuleCondition {
  field: string
  operator: ConditionOperator
  value: unknown
}

/** Le schéma exact : une clé `all`, une liste plate. */
export interface RuleConditions {
  all: RuleCondition[]
}

/**
 * Règle de communication — `CommunicationRuleResource`.
 *
 * Elle ne porte **pas** de canal : celui-ci vient du modèle. Le §158 l'interdit
 * explicitement — deux sources pour un même fait finiraient par se contredire.
 *
 * Elle ne stocke pas non plus l'adresse du destinataire : `recipientRole` dit
 * *quel rôle*, et le serveur résout le contact au moment de créer le message
 * (§35). Un contact qui change plus tard ne réécrit donc pas la règle.
 */
export interface CommunicationRule {
  id: string
  organizationId: string
  serviceId: string | null
  serviceName?: string | null
  templateId: string
  template?: Pick<Template, 'id' | 'code' | 'name' | 'channel'>
  eventType: CommunicationEventType
  recipientRole: RecipientRole
  delayValue: number
  delayUnit: DelayUnit
  conditions: RuleConditions | null
  isAutomatic: boolean
  isActive: boolean
  communicationsCount?: number
  createdAt: string
  updatedAt: string
}

/**
 * La règle a déjà produit des messages : le serveur refuse sa suppression.
 *
 * Le §145 privilégie la désactivation — elle cesse de produire sans effacer ce
 * qu'elle a produit.
 */
export function isRuleInUse(rule: CommunicationRule): boolean {
  return (rule.communicationsCount ?? 0) > 0
}
