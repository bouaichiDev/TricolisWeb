import { Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { FormulaTester } from './FormulaTester'
import { usePricingVariables, useSaveRule } from '../hooks/usePricing'
import { CONDITION_OPERATORS, type PriceRule } from '../types/pricing'
import { useServiceList } from '@/modules/services/hooks/useServices'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { useApiMessage } from '@/shared/hooks/useApiMessage'

interface PriceRuleDialogProps {
  priceListId: string
  rule: PriceRule | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** Sentinelle « toute prestation » : Radix refuse une option de valeur vide. */
const ANY_SERVICE = 'any'

interface ConditionDraft {
  variable: string
  operator: string
  valueFrom: string
  valueTo: string
}

/**
 * Écrire une règle : sa formule, et ce qui la rend applicable.
 *
 * Le testeur est dans le dialogue, sur la formule en cours : vérifier avant
 * d'enregistrer évite de découvrir la faute à la première facture. Il appelle
 * le serveur, donc le moteur réel.
 *
 * Sans service, la règle vaut pour toute prestation que ses conditions
 * acceptent. C'est utile — un forfait — et dangereux : une règle générique sans
 * condition attrape tout, ce que l'écran signale.
 */
export function PriceRuleDialog({ priceListId, rule, open, onOpenChange }: PriceRuleDialogProps) {
  const { t } = useTranslation()
  const save = useSaveRule(priceListId)
  const failure = useApiMessage(save.error)
  const services = useServiceList({ page: 1, perPage: 100 })
  // Le catalogue de la plateforme fait foi : une condition ne porte que sur une
  // variable qu'un superadmin a declaree.
  const catalogue = usePricingVariables()
  const conditionVariables = (catalogue.data ?? []).filter((variable) => variable.isActive)

  const [code, setCode] = useState(rule?.code ?? '')
  const [name, setName] = useState(rule?.name ?? '')
  const [formula, setFormula] = useState(rule?.formula ?? '')
  const [serviceId, setServiceId] = useState(rule?.serviceId ?? ANY_SERVICE)
  const [priority, setPriority] = useState(String(rule?.priority ?? 100))
  const [conditions, setConditions] = useState<ConditionDraft[]>(
    (rule?.conditions ?? []).map((condition) => ({
      variable: condition.variable,
      operator: condition.operator,
      valueFrom: condition.valueFrom ?? '',
      valueTo: condition.valueTo ?? '',
    })),
  )

  const ready = code.trim() !== '' && name.trim() !== '' && formula.trim() !== ''

  const submit = () => {
    save.mutate(
      {
        id: rule?.id,
        payload: {
          code: code.trim(),
          name: name.trim(),
          formula: formula.trim(),
          serviceId: serviceId === ANY_SERVICE ? null : serviceId,
          priority: Number.parseInt(priority, 10) || 100,
          conditions: conditions
            .filter((condition) => condition.valueFrom.trim() !== '')
            .map((condition) => ({
              variable: condition.variable,
              operator: condition.operator,
              valueFrom: condition.valueFrom.trim(),
              valueTo: condition.valueTo.trim() || null,
            })),
        },
      },
      { onSuccess: () => onOpenChange(false) },
    )
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>{rule ? t('pricing.rules.edit') : t('pricing.rules.create')}</DialogTitle>
          <DialogDescription>{t('pricing.rules.hint')}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={failure} />

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="flex flex-col gap-2">
            <Label htmlFor="rule-code">{t('pricing.rules.fields.code')}</Label>
            <Input id="rule-code" value={code} onChange={(e) => setCode(e.target.value)} required />
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="rule-name">{t('pricing.rules.fields.name')}</Label>
            <Input id="rule-name" value={name} onChange={(e) => setName(e.target.value)} required />
          </div>

          <AsyncSelect
            label={t('pricing.rules.fields.service')}
            value={serviceId}
            onChange={setServiceId}
            options={[
              { value: ANY_SERVICE, label: t('pricing.rules.anyService') },
              ...(services.data?.data ?? []).map((service) => ({
                value: service.id,
                label: service.name,
                hint: service.code,
              })),
            ]}
            isLoading={services.isPending}
          />

          <div className="flex flex-col gap-2">
            <Label htmlFor="rule-priority">{t('pricing.rules.fields.priority')}</Label>
            <Input
              id="rule-priority"
              type="number"
              min={0}
              value={priority}
              onChange={(e) => setPriority(e.target.value)}
            />
            <p className="text-xs text-muted-foreground">{t('pricing.rules.priorityHint')}</p>
          </div>
        </div>

        <FormulaTester formula={formula} onFormulaChange={setFormula} />

        <div className="flex flex-col gap-3">
          <div className="flex items-center justify-between">
            <Label>{t('pricing.rules.conditions')}</Label>
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={() =>
                setConditions([
                  ...conditions,
                  { variable: 'code_postal', operator: 'between', valueFrom: '', valueTo: '' },
                ])
              }
            >
              {t('pricing.rules.addCondition')}
            </Button>
          </div>

          <p className="text-xs text-muted-foreground">{t('pricing.rules.conditionsHint')}</p>

          {conditions.map((condition, index) => (
            <div key={index} className="flex flex-wrap items-end gap-2 rounded-md border p-2">
              <select
                aria-label={t('pricing.rules.fields.variable')}
                value={condition.variable}
                onChange={(e) => {
                  const next = [...conditions]
                  next[index] = { ...condition, variable: e.target.value }
                  setConditions(next)
                }}
                className="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
              >
                {conditionVariables.map((variable) => (
                  <option key={variable.code} value={variable.code}>
                    {variable.code}
                  </option>
                ))}
              </select>

              <select
                aria-label={t('pricing.rules.fields.operator')}
                value={condition.operator}
                onChange={(e) => {
                  const next = [...conditions]
                  next[index] = { ...condition, operator: e.target.value }
                  setConditions(next)
                }}
                className="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
              >
                {CONDITION_OPERATORS.map((operator) => (
                  <option key={operator} value={operator}>
                    {operator}
                  </option>
                ))}
              </select>

              <Input
                aria-label={t('pricing.rules.fields.valueFrom')}
                value={condition.valueFrom}
                onChange={(e) => {
                  const next = [...conditions]
                  next[index] = { ...condition, valueFrom: e.target.value }
                  setConditions(next)
                }}
                className="h-8 w-28"
              />

              {condition.operator === 'between' ? (
                <Input
                  aria-label={t('pricing.rules.fields.valueTo')}
                  value={condition.valueTo}
                  onChange={(e) => {
                    const next = [...conditions]
                    next[index] = { ...condition, valueTo: e.target.value }
                    setConditions(next)
                  }}
                  className="h-8 w-28"
                />
              ) : null}

              <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={t('common.delete')}
                onClick={() => setConditions(conditions.filter((_, at) => at !== index))}
              >
                <Trash2 className="size-4" aria-hidden />
              </Button>
            </div>
          ))}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button disabled={!ready || save.isPending} onClick={submit}>
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
