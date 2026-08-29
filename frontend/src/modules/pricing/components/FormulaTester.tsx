import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { useCheckFormula } from '../hooks/usePricing'
import { FORMULA_VARIABLES } from '../types/pricing'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

interface FormulaTesterProps {
  formula: string
  onFormulaChange?: (formula: string) => void
  /** Le champ de formule est ailleurs : on ne fait qu'essayer celle-ci. */
  readOnlyFormula?: boolean
}

/**
 * Écrire une formule, et voir ce qu'elle donne.
 *
 * **Le calcul vient du serveur** (§169AF) : un évaluateur écrit en JavaScript
 * finirait par diverger du moteur réel, et l'écran annoncerait un prix que la
 * facture ne confirmerait pas. Chaque essai est un appel.
 *
 * La validité de la formule et le succès de l'essai sont deux choses : une
 * formule juste peut échouer sur des valeurs — une division par zéro — sans
 * être fautive. Les confondre ferait corriger une formule correcte.
 */
export function FormulaTester({
  formula,
  onFormulaChange,
  readOnlyFormula = false,
}: FormulaTesterProps) {
  const { t } = useTranslation()
  const [values, setValues] = useState<Record<string, string>>({})
  const check = useCheckFormula()

  const outcome = check.data

  const run = () => {
    const variables: Record<string, number | null> = {}

    for (const [name, raw] of Object.entries(values)) {
      const parsed = Number.parseFloat(raw)
      if (!Number.isNaN(parsed)) variables[name] = parsed
    }

    check.mutate({ formula, variables })
  }

  return (
    <div className="flex flex-col gap-4 rounded-md border p-4">
      <div className="flex flex-col gap-2">
        <Label htmlFor="formula-tester">{t('pricing.tester.formula')}</Label>
        <Input
          id="formula-tester"
          value={formula}
          onChange={(event) => onFormulaChange?.(event.target.value)}
          readOnly={readOnlyFormula}
          placeholder="({P:poids}/{V:100})*{V:25}"
          className="font-mono"
        />
        <p className="text-xs text-muted-foreground">{t('pricing.tester.syntax')}</p>
      </div>

      <div className="flex flex-wrap gap-1">
        {FORMULA_VARIABLES.map((name) => (
          <button
            key={name}
            type="button"
            disabled={readOnlyFormula || onFormulaChange === undefined}
            onClick={() => onFormulaChange?.(`${formula}{P:${name}}`)}
            className="rounded-full border px-2 py-0.5 font-mono text-xs hover:bg-accent disabled:opacity-60"
          >
            {`{P:${name}}`}
          </button>
        ))}
      </div>

      <div className="grid gap-3 sm:grid-cols-3">
        {FORMULA_VARIABLES.map((name) => (
          <div key={name} className="flex flex-col gap-1">
            <Label htmlFor={`var-${name}`} className="text-xs">
              {name}
            </Label>
            <Input
              id={`var-${name}`}
              type="number"
              step="0.001"
              value={values[name] ?? ''}
              onChange={(event) => setValues({ ...values, [name]: event.target.value })}
              className="h-8"
            />
          </div>
        ))}
      </div>

      <div className="flex items-center gap-3">
        <Button size="sm" onClick={run} disabled={formula.trim() === '' || check.isPending}>
          {t('pricing.tester.run')}
        </Button>

        {outcome ? (
          <span className="flex flex-wrap items-center gap-2 text-sm">
            <Badge variant={outcome.valid ? 'default' : 'destructive'}>
              {outcome.valid ? t('pricing.tester.valid') : t('pricing.tester.invalid')}
            </Badge>

            {outcome.result?.amount ? (
              <span className="font-medium tabular-nums">
                {t('pricing.tester.result', { amount: outcome.result.amount })}
              </span>
            ) : null}

            {outcome.error ?? outcome.result?.error ? (
              <span className="text-destructive">{outcome.error ?? outcome.result?.error}</span>
            ) : null}
          </span>
        ) : null}
      </div>

      {outcome?.variables.length ? (
        <p className="text-xs text-muted-foreground">
          {t('pricing.tester.uses', { variables: outcome.variables.join(', ') })}
        </p>
      ) : null}
    </div>
  )
}
