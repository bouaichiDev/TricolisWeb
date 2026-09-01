import { z } from 'zod'

import type { CommunicationRulePayload } from '../api/communication-rules.api'
import {
  CONDITION_OPERATORS,
  DELAY_UNITS,
  operatorTakesList,
  type CommunicationRule,
  type RuleCondition,
  type RuleConditions,
} from '../types/communicationRule'

/** Motif de champ de `CommunicationRuleConditionEvaluator`. */
export const CONDITION_FIELD_PATTERN = /^[a-z][a-z0-9_]{0,63}$/

/** Vingt clauses au plus, comme `MAX_CONDITIONS` côté serveur. */
export const MAX_CONDITIONS = 20

const conditionSchema = z.object({
  field: z.string().regex(CONDITION_FIELD_PATTERN, 'communicationRules.errors.field'),
  operator: z.enum(CONDITION_OPERATORS),
  value: z.string(),
})

/**
 * Contraintes reprises de `StoreCommunicationRuleRequest`.
 *
 * `delayValue` est borné à zéro : le serveur le refuse négatif, et son message
 * dit pourquoi — un délai négatif ferait envoyer *avant* l'événement, ce que
 * rien ne sait faire.
 *
 * Aucun `channel` : il vient du modèle. Le §158 interdit de le porter ici, et
 * deux sources pour un même fait finiraient par se contredire.
 */
export const communicationRuleSchema = z.object({
  serviceId: z.string(),
  templateId: z.string().min(1, 'validation.required'),
  eventType: z.string().min(1, 'validation.required'),
  recipientRole: z.string().min(1, 'validation.required'),
  delayValue: z.number().int().min(0, 'validation.min').max(100000, 'validation.max'),
  delayUnit: z.enum(DELAY_UNITS),
  conditions: z.array(conditionSchema).max(MAX_CONDITIONS, 'validation.max'),
  isAutomatic: z.boolean(),
  isActive: z.boolean(),
})

export type RuleFormValues = z.infer<typeof communicationRuleSchema>

export const RULE_FORM_DEFAULTS: RuleFormValues = {
  serviceId: '',
  templateId: '',
  eventType: 'service_completed',
  recipientRole: 'customer',
  delayValue: 0,
  delayUnit: 'minutes',
  conditions: [],
  isAutomatic: true,
  isActive: true,
}

export function isRuleComplete(values: RuleFormValues): boolean {
  return communicationRuleSchema.safeParse(values).success
}

/**
 * La valeur d'une clause : liste pour `in` et `not_in`, scalaire sinon.
 *
 * La saisie reste du texte — l'évaluateur compare des faits déjà extraits, et
 * inventer un typage ici ferait diverger la comparaison.
 */
function toConditionValue(condition: RuleFormValues['conditions'][number]): unknown {
  if (!operatorTakesList(condition.operator)) return condition.value

  return condition.value
    .split(',')
    .map((part) => part.trim())
    .filter((part) => part !== '')
}

export function toRulePayload(values: RuleFormValues): CommunicationRulePayload {
  const conditions: RuleConditions | null =
    values.conditions.length === 0
      ? null
      : {
          all: values.conditions.map(
            (condition): RuleCondition => ({
              field: condition.field.trim(),
              operator: condition.operator,
              value: toConditionValue(condition),
            }),
          ),
        }

  return {
    serviceId: values.serviceId.trim() === '' ? null : values.serviceId,
    templateId: values.templateId,
    eventType: values.eventType,
    recipientRole: values.recipientRole,
    delayValue: values.delayValue,
    delayUnit: values.delayUnit,
    conditions,
    isAutomatic: values.isAutomatic,
    isActive: values.isActive,
  }
}

export function toRuleFormValues(rule: CommunicationRule): RuleFormValues {
  return {
    serviceId: rule.serviceId ?? '',
    templateId: rule.templateId,
    eventType: rule.eventType,
    recipientRole: rule.recipientRole,
    delayValue: rule.delayValue,
    delayUnit: rule.delayUnit,
    conditions: (rule.conditions?.all ?? []).map((condition) => ({
      field: condition.field,
      operator: condition.operator,
      value: Array.isArray(condition.value)
        ? condition.value.join(', ')
        : String(condition.value ?? ''),
    })),
    isAutomatic: rule.isAutomatic,
    isActive: rule.isActive,
  }
}
