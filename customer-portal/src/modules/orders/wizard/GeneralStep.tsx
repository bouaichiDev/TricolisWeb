import { useId } from 'react'
import type { UseFormReturn } from 'react-hook-form'

import { Field } from '@/shared/components/form/Field'
import { Label } from '@/shared/components/ui/label'
import { Textarea } from '@/shared/components/ui/textarea'

import type { OrderFormValues } from '../form/orderSchema'

/** Étape 1 : les références du client et les consignes générales. */
export function GeneralStep({ form }: { form: UseFormReturn<OrderFormValues> }) {
  const remarkId = useId()

  return (
    <div className="grid gap-4">
      <div className="grid gap-4 sm:grid-cols-2">
        <Field form={form} name="customerReference" label="Votre référence" placeholder="Ex. IKEA-4587" />
        <Field form={form} name="externalReference" label="Référence externe (facultatif)" />
      </div>
      <div className="grid gap-1.5">
        <Label htmlFor={remarkId}>Consignes pour le transporteur</Label>
        <Textarea id={remarkId} rows={4} placeholder="Accès, étage, contraintes de livraison…" {...form.register('workerRemark')} />
      </div>
    </div>
  )
}
