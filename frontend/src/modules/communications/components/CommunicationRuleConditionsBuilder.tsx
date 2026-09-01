import { Plus, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/shared/components/ui/select'

import { CONDITION_OPERATORS, operatorTakesList } from '../types/communicationRule'
import { CONDITION_FIELD_PATTERN, MAX_CONDITIONS, type RuleFormValues } from '../schemas/ruleSchema'

interface ConditionsBuilderProps {
  conditions: RuleFormValues['conditions']
  onChange: (conditions: RuleFormValues['conditions']) => void
}

/**
 * Conditions d'une règle, saisies clause par clause.
 *
 * Le schéma du serveur est **stable et minimal** : une clé `all`, une liste
 * plate, huit opérateurs. Ni `any`, ni `not`, ni imbrication. Un éditeur JSON
 * libre laisserait composer ce que l'évaluateur refuse, et l'erreur
 * n'arriverait qu'à l'enregistrement — ou pire, au premier événement.
 *
 * Le **champ** reste libre : l'évaluateur compare des faits déjà extraits, et
 * aucune liste de champs autorisés n'existe côté serveur. Le §159 interdit
 * d'en inventer une. Le motif, lui, est celui du serveur : minuscules, chiffres
 * et tirets bas — ni point, ni parenthèse, ni chemin.
 *
 * Sans clause, la règle est **inconditionnelle** : `conditions` part à `null`,
 * ce qui est la valeur que le serveur attend pour « toujours ».
 */
export function CommunicationRuleConditionsBuilder({
  conditions,
  onChange,
}: ConditionsBuilderProps) {
  const { t } = useTranslation()

  const patch = (index: number, next: Partial<RuleFormValues['conditions'][number]>) =>
    onChange(conditions.map((item, position) => (position === index ? { ...item, ...next } : item)))

  return (
    <section className="flex flex-col gap-3 border-t pt-4">
      <div>
        <Label>{t('communicationRules.fields.conditions')}</Label>
        <p className="text-xs text-muted-foreground">
          {conditions.length === 0
            ? t('communicationRules.conditionsEmpty')
            : t('communicationRules.conditionsHint')}
        </p>
      </div>

      <ul className="flex flex-col gap-2">
        {conditions.map((condition, index) => {
          const invalidField =
            condition.field !== '' && !CONDITION_FIELD_PATTERN.test(condition.field)

          return (
            <li key={index} className="flex flex-wrap items-start gap-2">
              <div className="flex-1 min-w-40">
                <Input
                  value={condition.field}
                  onChange={(event) => patch(index, { field: event.target.value })}
                  placeholder="order_status"
                  aria-label={t('communicationRules.fields.conditionField')}
                  aria-invalid={invalidField}
                />
                {invalidField ? (
                  <p className="mt-1 text-xs text-destructive">
                    {t('communicationRules.errors.field')}
                  </p>
                ) : null}
              </div>

              <Select
                value={condition.operator}
                onValueChange={(operator) =>
                  patch(index, { operator: operator as (typeof CONDITION_OPERATORS)[number] })
                }
              >
                <SelectTrigger
                  className="w-36"
                  aria-label={t('communicationRules.fields.conditionOperator')}
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {CONDITION_OPERATORS.map((operator) => (
                    <SelectItem key={operator} value={operator}>
                      {t(`communicationRules.operators.${operator}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

              <Input
                className="flex-1 min-w-40"
                value={condition.value}
                onChange={(event) => patch(index, { value: event.target.value })}
                placeholder={
                  operatorTakesList(condition.operator)
                    ? t('communicationRules.listPlaceholder')
                    : 'confirmed'
                }
                aria-label={t('communicationRules.fields.conditionValue')}
              />

              <Button
                type="button"
                variant="ghost"
                size="icon"
                title={t('common.remove')}
                aria-label={t('common.remove')}
                onClick={() => onChange(conditions.filter((_, position) => position !== index))}
              >
                <Trash2 className="size-4" aria-hidden />
              </Button>
            </li>
          )
        })}
      </ul>

      <div>
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={conditions.length >= MAX_CONDITIONS}
          onClick={() => onChange([...conditions, { field: '', operator: 'eq', value: '' }])}
        >
          <Plus className="size-4" aria-hidden />
          {t('communicationRules.addCondition')}
        </Button>
      </div>
    </section>
  )
}
