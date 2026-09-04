import { Loader2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'

import { useAgencyOptions, useCustomerOptions } from '../../hooks/useOrderScope'
import type { OrderDraftController } from '../../hooks/useOrderDraft'
import type { OrderErrorReport, OrderStep } from '../../schemas/orderErrors'
import { OrderGeneralStep } from './OrderGeneralStep'
import { OrderLinesStep } from './OrderLinesStep'
import { OrderPackagesStep } from './OrderPackagesStep'
import { OrderReviewStep } from './OrderReviewStep'
import { OrderServicesStep } from './OrderServicesStep'
import { OrderWizardNav } from './OrderWizardNav'
import { ORDER_STEPS } from './steps'

interface OrderFormShellProps {
  controller: OrderDraftController
  report: OrderErrorReport
  isSubmitting: boolean
  onSubmit: () => void
  onCancel: () => void
  submitLabel?: string
}

/**
 * Parcours de création d'une commande, en cinq étapes.
 *
 * Cinq et non six : le modèle ne comporte pas d'entité « arrêt ». L'adresse, le
 * créneau et les contacts sont portés par `OrderService`, donc décrits à l'étape
 * Services.
 *
 * Rien n'est envoyé avant la dernière étape : `POST /orders` crée l'ensemble en
 * une transaction, et créer les sous-ressources au fil de l'eau laisserait des
 * commandes à moitié saisies derrière chaque abandon.
 */
export function OrderFormShell({
  controller,
  report,
  isSubmitting,
  onSubmit,
  onCancel,
  submitLabel,
}: OrderFormShellProps) {
  const { t } = useTranslation()
  const [step, setStep] = useState<OrderStep>('general')

  const customers = useCustomerOptions('')
  const agencies = useAgencyOptions()

  const index = ORDER_STEPS.indexOf(step)
  const isLast = index === ORDER_STEPS.length - 1

  const nameOf = (options: { value: string; label: string }[], id: string): string =>
    options.find((option) => option.value === id)?.label ?? ''

  return (
    <div className="flex flex-col gap-6">
      <OrderWizardNav current={step} onSelect={setStep} stepsInError={report.stepsInError} />

      {report.message !== null ? (
        <Alert variant="destructive">
          <AlertDescription>{report.message}</AlertDescription>
        </Alert>
      ) : report.stepsInError.length > 0 ? (
        <Alert variant="destructive">
          <AlertDescription>{t('orders.wizard.serverRejected')}</AlertDescription>
        </Alert>
      ) : null}

      {step === 'general' ? <OrderGeneralStep controller={controller} report={report} /> : null}
      {step === 'lines' ? <OrderLinesStep controller={controller} report={report} /> : null}
      {step === 'packages' ? <OrderPackagesStep controller={controller} report={report} /> : null}
      {step === 'services' ? <OrderServicesStep controller={controller} report={report} /> : null}
      {step === 'review' ? (
        <OrderReviewStep
          draft={controller.draft}
          customerName={nameOf(customers.options, controller.draft.customerId)}
          agencyName={nameOf(agencies.options, controller.draft.agencyId)}
        />
      ) : null}

      <div className="flex flex-wrap justify-between gap-2 border-t pt-4">
        <Button type="button" variant="ghost" onClick={onCancel} disabled={isSubmitting}>
          {t('common.cancel')}
        </Button>

        <div className="flex gap-2">
          <Button
            type="button"
            variant="outline"
            onClick={() => setStep(ORDER_STEPS[index - 1])}
            disabled={index === 0 || isSubmitting}
          >
            {t('orders.wizard.previous')}
          </Button>

          {isLast ? (
            <Button type="button" onClick={onSubmit} disabled={isSubmitting}>
              {isSubmitting ? <Loader2 className="size-4 animate-spin" aria-hidden /> : null}
              {submitLabel ?? t('orders.wizard.submit')}
            </Button>
          ) : (
            <Button type="button" onClick={() => setStep(ORDER_STEPS[index + 1])}>
              {t('orders.wizard.next')}
            </Button>
          )}
        </div>
      </div>
    </div>
  )
}
