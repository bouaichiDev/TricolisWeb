import { useTranslation } from 'react-i18next'

import type { Template } from '@/modules/templates/types/template'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'

import type { RuleFormValues } from '../schemas/ruleSchema'

interface RuleSummaryProps {
  values: RuleFormValues
  template: Template | undefined
  serviceName: string | undefined
}

/**
 * La règle en une phrase, avant d'enregistrer.
 *
 * Six champs séparés se lisent mal : « `service_completed` / `delivery_contact`
 * / 10 / `hours` » n'apprend pas quand le client recevra quoi. Le résumé le
 * dit, ce que le §49 demande.
 *
 * C'est une aide de lecture, pas une validation : le serveur reste seul juge.
 *
 * Deux réserves y sont dites plutôt que cachées :
 *
 * - une règle **non automatique** ne produit rien d'elle-même ;
 * - aucun événement n'est encore émis par la plateforme, donc aucune règle ne
 *   se déclenche aujourd'hui. Le taire ferait attendre des messages qui ne
 *   partiront pas.
 */
export function CommunicationRuleSummary({ values, template, serviceName }: RuleSummaryProps) {
  const { t } = useTranslation()

  const rows: Array<[string, string]> = [
    [t('communicationRules.summary.when'), t(`communicationEvents.${values.eventType}`)],
    [t('communicationRules.summary.scope'), serviceName ?? t('communicationRules.allServices')],
    [
      t('communicationRules.summary.wait'),
      values.delayValue === 0
        ? t('communicationRules.summary.immediately')
        : `${values.delayValue} ${t(`communicationRules.delayUnits.${values.delayUnit}`)}`,
    ],
    [t('communicationRules.summary.send'), template?.name ?? t('communicationRules.noTemplate')],
    [
      t('communicationRules.summary.channel'),
      template?.channel === undefined || template.channel === null
        ? '—'
        : t(`communicationChannels.${template.channel}`),
    ],
    [t('communicationRules.summary.to'), t(`recipientRoles.${values.recipientRole}`)],
    [
      t('communicationRules.summary.conditions'),
      values.conditions.length === 0
        ? t('communicationRules.summary.always')
        : values.conditions
            .map(
              (condition) =>
                `${condition.field} ${t(`communicationRules.operators.${condition.operator}`)} ${condition.value}`,
            )
            .join(' ; '),
    ],
  ]

  return (
    <section className="flex flex-col gap-2 border-t pt-4">
      <p className="text-sm font-medium">{t('communicationRules.summary.title')}</p>

      <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
        {rows.map(([label, value]) => (
          <div key={label} className="contents">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value}</dd>
          </div>
        ))}
      </dl>

      {values.isAutomatic ? (
        <Alert>
          <AlertDescription>{t('communicationRules.notWiredYet')}</AlertDescription>
        </Alert>
      ) : (
        <Alert>
          <AlertDescription>{t('communicationRules.manualOnly')}</AlertDescription>
        </Alert>
      )}
    </section>
  )
}
