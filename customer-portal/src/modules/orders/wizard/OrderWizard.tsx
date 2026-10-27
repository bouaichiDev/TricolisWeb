import { zodResolver } from '@hookform/resolvers/zod'
import { ArrowLeft, ArrowRight, Check, Loader2 } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'

import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { cn } from '@/shared/utils/cn'

import { applyOrderErrors, STEP_FIELDS, STEPS, type StepKey } from '../form/orderErrors'
import { toPayload } from '../form/orderMapping'
import { orderSchema, type OrderFormValues, type OrderPayload } from '../form/orderSchema'
import type { FormOptions } from '../types'
import { DocumentsStep } from './DocumentsStep'
import { GeneralStep } from './GeneralStep'
import { LinesStep } from './LinesStep'
import { PackagesStep } from './PackagesStep'
import { ReviewStep } from './ReviewStep'
import { ServicesStep } from './ServicesStep'

interface OrderWizardProps {
  initial: OrderFormValues
  options: FormOptions
  submitLabel: string
  /** Étape « Documents » : seulement pour qui peut déposer sur la commande. */
  allowDocuments?: boolean
  onSubmit: (payload: OrderPayload, documents: File[]) => Promise<void>
}

/**
 * Le parcours guidé de création et de modification, étape par étape, chacune
 * validée avant la suivante, puis un récapitulatif.
 *
 * La soumission est verrouillée tant qu'une requête est en cours : un double
 * clic ne crée pas deux commandes. Les erreurs du backend reviennent sur le
 * champ fautif, et le wizard rouvre l'étape concernée.
 */
export function OrderWizard({ initial, options, submitLabel, allowDocuments = false, onSubmit }: OrderWizardProps) {
  const steps = STEPS.filter((item) => item.key !== 'documents' || allowDocuments)
  const [index, setIndex] = useState(0)
  const [documents, setDocuments] = useState<File[]>([])
  const [error, setError] = useState<string | null>(null)
  const form = useForm<OrderFormValues>({ resolver: zodResolver(orderSchema), defaultValues: initial, mode: 'onTouched' })
  const submitting = form.formState.isSubmitting
  const current = steps[index]
  const last = index === steps.length - 1

  const goTo = (key: StepKey) => setIndex(Math.max(steps.findIndex((item) => item.key === key), 0))

  const next = async () => {
    if (await form.trigger(STEP_FIELDS[current.key])) setIndex(index + 1)
  }

  const submit = form.handleSubmit(async (values) => {
    setError(null)

    try {
      await onSubmit(toPayload(values), documents)
    } catch (caught) {
      const result = applyOrderErrors(form, caught)
      setError(result.message)
      if (result.step !== null) goTo(result.step)
    }
  })

  return (
    <form onSubmit={(event) => (last ? void submit(event) : event.preventDefault())} noValidate className="flex flex-col gap-6">
      <ol className="flex flex-wrap gap-2" aria-label="Étapes">
        {steps.map((item, position) => (
          <li key={item.key}>
            <button
              type="button"
              disabled={position > index}
              onClick={() => setIndex(position)}
              aria-current={position === index ? 'step' : undefined}
              className={cn(
                'rounded-full border px-3 py-1 text-sm',
                position === index && 'bg-primary text-primary-foreground border-primary',
                position < index && 'border-primary text-primary',
              )}
            >
              {position + 1}. {item.label}
            </button>
          </li>
        ))}
      </ol>

      {error && (
        <Alert variant="destructive" role="alert">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      <SectionCard title={`${index + 1}. ${current.label}`}>
        {current.key === 'general' && <GeneralStep form={form} />}
        {current.key === 'lines' && <LinesStep form={form} catalogEnabled={options.catalogEnabled} />}
        {current.key === 'packages' && <PackagesStep form={form} options={options} />}
        {current.key === 'services' && <ServicesStep form={form} options={options} />}
        {current.key === 'documents' && <DocumentsStep files={documents} onChange={setDocuments} />}
        {current.key === 'review' && <ReviewStep form={form} options={options} documents={documents} />}
      </SectionCard>

      <div className="flex justify-between">
        <Button type="button" variant="outline" disabled={index === 0 || submitting} onClick={() => setIndex(index - 1)}>
          <ArrowLeft className="size-4" /> Précédent
        </Button>
        {/* Des `key` distinctes : sans elles, React réutilise le même bouton et
            le passe en `submit` pendant le clic sur « Suivant », qui soumet alors
            la commande dès l'arrivée sur le récapitulatif. */}
        {last ? (
          <Button key="submit" type="submit" disabled={submitting}>
            {submitting ? <Loader2 className="size-4 animate-spin" /> : <Check className="size-4" />} {submitLabel}
          </Button>
        ) : (
          <Button key="next" type="button" onClick={() => void next()}>
            Suivant <ArrowRight className="size-4" />
          </Button>
        )}
      </div>
    </form>
  )
}
