import { AlertCircle, Check } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { cn } from '@/shared/utils/cn'

import type { OrderStep } from '../../schemas/orderErrors'
import { ORDER_STEPS } from './steps'

interface OrderWizardNavProps {
  current: OrderStep
  onSelect: (step: OrderStep) => void
  stepsInError: OrderStep[]
}

/**
 * Fil des étapes du formulaire de commande.
 *
 * Les étapes restent toutes accessibles : après un refus du serveur, l'erreur
 * peut concerner une étape déjà franchie, et obliger à repasser par les
 * suivantes ferait perdre du temps sans rien protéger — la validation qui
 * compte est celle de l'envoi.
 */
export function OrderWizardNav({ current, onSelect, stepsInError }: OrderWizardNavProps) {
  const { t } = useTranslation()
  const currentIndex = ORDER_STEPS.indexOf(current)

  return (
    <nav aria-label={t('orders.create')}>
      <ol className="flex flex-wrap gap-2">
        {ORDER_STEPS.map((step, index) => {
          const inError = stepsInError.includes(step)
          const done = index < currentIndex && !inError

          return (
            <li key={step}>
              <button
                type="button"
                onClick={() => onSelect(step)}
                aria-current={step === current ? 'step' : undefined}
                className={cn(
                  'flex items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors',
                  step === current
                    ? 'border-primary bg-primary/10 font-medium text-primary'
                    : 'border-border text-muted-foreground hover:bg-accent',
                  inError && 'border-destructive text-destructive',
                )}
              >
                <span
                  className={cn(
                    'flex size-5 items-center justify-center rounded-full text-xs',
                    step === current ? 'bg-primary text-primary-foreground' : 'bg-muted',
                    inError && 'bg-destructive text-destructive-foreground',
                  )}
                >
                  {inError ? (
                    <AlertCircle className="size-3.5" aria-label={t('orders.wizard.stepInError')} />
                  ) : done ? (
                    <Check className="size-3.5" aria-hidden />
                  ) : (
                    index + 1
                  )}
                </span>
                {t(`orders.steps.${step}`)}
              </button>
            </li>
          )
        })}
      </ol>
    </nav>
  )
}
