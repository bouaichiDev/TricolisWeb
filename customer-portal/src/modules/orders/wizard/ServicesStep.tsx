import { Plus } from 'lucide-react'
import { useFieldArray, type UseFormReturn } from 'react-hook-form'

import { fieldError } from '@/shared/components/form/fieldError'
import { Button } from '@/shared/components/ui/button'

import { emptyService } from '../form/orderMapping'
import type { OrderFormValues } from '../form/orderSchema'
import type { FormOptions } from '../types'
import { ServiceCard } from './ServiceCard'

/** Étape 4 : les services demandés — au moins un, vingt au plus. */
export function ServicesStep({ form, options }: { form: UseFormReturn<OrderFormValues>; options: FormOptions }) {
  const services = useFieldArray({ control: form.control, name: 'services', keyName: 'fieldKey' })
  const rootError = fieldError(form, 'services')

  return (
    <div className="space-y-3">
      {rootError && <p className="text-destructive text-sm">{rootError}</p>}
      {services.fields.map((service, index) => (
        <ServiceCard
          key={service.fieldKey}
          form={form}
          index={index}
          options={options}
          onRemove={services.fields.length > 1 ? () => services.remove(index) : undefined}
        />
      ))}
      <Button type="button" variant="outline" disabled={services.fields.length >= 20} onClick={() => services.append(emptyService())}>
        <Plus className="size-4" /> Ajouter un service
      </Button>
    </div>
  )
}
