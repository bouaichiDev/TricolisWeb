import { Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'

import type { OrderDraftController } from '../../hooks/useOrderDraft'
import type { OrderErrorReport } from '../../schemas/orderErrors'
import { OrderServiceCard } from './OrderServiceCard'

interface OrderServicesStepProps {
  controller: OrderDraftController
  report: OrderErrorReport
}

/**
 * Services de la commande.
 *
 * Le modèle ne connaît pas d'entité « arrêt » : c'est `OrderService` qui porte
 * l'adresse, le créneau, les contacts et les colis. Cette étape est donc la
 * seule à décrire le déroulé sur le terrain, et au moins un service est requis.
 */
export function OrderServicesStep({ controller, report }: OrderServicesStepProps) {
  const { t } = useTranslation()
  const { draft, addService, patchService, removeService } = controller

  const collectionIssue = report.issues.find((issue) => issue.field === 'services')

  return (
    <SectionCard
      title={t('orders.services.title')}
      description={t('orders.services.description')}
      actions={
        <Button type="button" variant="outline" size="sm" onClick={addService}>
          <Plus className="size-4" aria-hidden />
          {t('orders.services.add')}
        </Button>
      }
    >
      <div className="flex flex-col gap-4">
        {collectionIssue ? (
          <Alert variant="destructive">
            <AlertDescription>
              {t(collectionIssue.message, { defaultValue: collectionIssue.message })}
            </AlertDescription>
          </Alert>
        ) : null}

        <ul className="flex flex-col gap-4">
          {draft.services.map((service, index) => (
            <OrderServiceCard
              key={service.key}
              service={service}
              position={index + 1}
              customerId={draft.customerId}
              packages={draft.packages}
              report={report}
              onChange={(values) => patchService(service.key, values)}
              onRemove={() => removeService(service.key)}
              canRemove={draft.services.length > 1}
            />
          ))}
        </ul>

        <p className="text-xs text-muted-foreground">{t('orders.wizard.requiredServices')}</p>
      </div>
    </SectionCard>
  )
}
