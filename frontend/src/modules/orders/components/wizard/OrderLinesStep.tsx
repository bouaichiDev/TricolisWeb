import { Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { useCustomer } from '@/modules/customers/hooks/useCustomers'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { SectionCard } from '@/shared/components/layout/SectionCard'

import type { OrderDraftController } from '../../hooks/useOrderDraft'
import { issuesOf, type OrderErrorReport } from '../../schemas/orderErrors'
import { OrderLineCard } from './OrderLineCard'

interface OrderLinesStepProps {
  controller: OrderDraftController
  report: OrderErrorReport
}

/**
 * Contenu commandé.
 *
 * Le catalogue n'est proposé que si le client l'a activé : `catalogEnabled` est
 * une capacité du client, et proposer un catalogue à un client qui n'en a pas
 * mènerait à une liste vide sans explication. La saisie libre reste ouverte
 * dans tous les cas — le backend accepte une ligne sans article.
 */
export function OrderLinesStep({ controller, report }: OrderLinesStepProps) {
  const { t } = useTranslation()
  const { draft, addLine, patchLine, removeLine } = controller
  const customer = useCustomer(draft.customerId === '' ? undefined : draft.customerId)
  const catalogEnabled = customer.data?.catalogEnabled ?? false

  const collectionIssue = report.issues.find((issue) => issue.field === 'lines')

  return (
    <SectionCard
      title={t('orders.lines.title')}
      description={t('orders.lines.description')}
      actions={
        <Button type="button" variant="outline" size="sm" onClick={addLine}>
          <Plus className="size-4" aria-hidden />
          {t('orders.lines.add')}
        </Button>
      }
    >
      <div className="flex flex-col gap-4">
        {draft.customerId === '' ? (
          <Alert>
            <AlertDescription>{t('orders.lines.noCustomer')}</AlertDescription>
          </Alert>
        ) : !catalogEnabled && !customer.isPending ? (
          <Alert>
            <AlertDescription>{t('orders.lines.catalogDisabled')}</AlertDescription>
          </Alert>
        ) : null}

        {collectionIssue ? (
          <Alert variant="destructive">
            <AlertDescription>
              {t(collectionIssue.message, { defaultValue: collectionIssue.message })}
            </AlertDescription>
          </Alert>
        ) : null}

        <ul className="flex flex-col gap-4">
          {draft.lines.map((line, index) => (
            <OrderLineCard
              key={line.key}
              line={line}
              position={index + 1}
              customerId={draft.customerId}
              catalogEnabled={catalogEnabled}
              issues={issuesOf(report, line.key)}
              onChange={(values) => patchLine(line.key, values)}
              onRemove={() => removeLine(line.key)}
              canRemove={draft.lines.length > 1}
            />
          ))}
        </ul>

        <p className="text-xs text-muted-foreground">{t('orders.wizard.requiredLines')}</p>
      </div>
    </SectionCard>
  )
}
