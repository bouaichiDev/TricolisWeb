import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { FormulaTester } from '../components/FormulaTester'
import { usePricingVariables } from '../hooks/usePricing'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'

/**
 * Écrire une formule et voir ce qu'elle donne, sans rien enregistrer.
 *
 * Le même moteur que le calcul réel : ce qu'on voit ici est ce que la facture
 * dira. C'est aussi l'endroit où apprendre la syntaxe sans risquer d'abîmer un
 * barème en production.
 */
export function FormulaTesterPage() {
  const { t } = useTranslation()
  const [formula, setFormula] = useState('({P:poids}/{V:100})*{V:25}')
  const catalogue = usePricingVariables()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('pricing.tester.title')} description={t('pricing.tester.subtitle')} />

      <SectionCard title={t('pricing.tester.section')}>
        <FormulaTester formula={formula} onFormulaChange={setFormula} />
      </SectionCard>

      <SectionCard title={t('pricing.tester.helpTitle')}>
        <div className="flex flex-col gap-3 text-sm">
          <p>{t('pricing.tester.helpSyntax')}</p>

          <ul className="flex flex-col gap-1">
            {(catalogue.data ?? [])
              .filter((variable) => variable.isActive && variable.kind === 'numeric')
              .map((variable) => (
                <li key={variable.code} className="flex gap-2">
                  <code className="font-mono text-xs">{`{P:${variable.code}}`}</code>
                  <span className="text-muted-foreground">
                    {variable.label}
                    {variable.unit ? ` (${variable.unit})` : ''}
                  </span>
                </li>
              ))}
          </ul>

          <p className="text-muted-foreground">{t('pricing.tester.helpExample')}</p>
          <code className="font-mono text-xs">({'{P:poids}'} / {'{V:100}'}) * {'{V:25}'}</code>
        </div>
      </SectionCard>
    </div>
  )
}
