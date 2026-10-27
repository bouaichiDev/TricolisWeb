import { Plus, Trash2 } from 'lucide-react'
import { useFieldArray, useWatch, type UseFormReturn } from 'react-hook-form'

import { EmptyState } from '@/shared/components/feedback/States'
import { Field } from '@/shared/components/form/Field'
import { SelectField } from '@/shared/components/form/SelectField'
import { Button } from '@/shared/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/shared/components/ui/card'

import { emptyPackage } from '../form/orderMapping'
import type { OrderFormValues } from '../form/orderSchema'
import type { FormOptions } from '../types'

const toOptions = (items: { id: string; name: string }[]) => items.map((item) => ({ value: item.id, label: item.name }))

/**
 * Étape 3 : les colis, facultatifs. Chacun peut recevoir tout ou partie des
 * lignes ; le backend refuse d'y mettre plus que la quantité commandée.
 */
export function PackagesStep({ form, options }: { form: UseFormReturn<OrderFormValues>; options: FormOptions }) {
  const packages = useFieldArray({ control: form.control, name: 'packages', keyName: 'fieldKey' })
  const lines = useWatch({ control: form.control, name: 'lines' }) ?? []
  const lineOptions = lines.map((line, index) => ({
    value: line.uid,
    label: `${index + 1}. ${line.articleLabel || line.name || 'Ligne sans libellé'}`,
  }))

  return (
    <div className="space-y-3">
      {packages.fields.length === 0 && (
        <EmptyState title="Aucun colis">Les colis sont facultatifs : votre transporteur peut les constituer lui-même.</EmptyState>
      )}
      {packages.fields.map((pkg, index) => (
        <Card key={pkg.fieldKey}>
          <CardHeader className="flex flex-row items-center justify-between space-y-0">
            <CardTitle className="text-base">Colis {index + 1}</CardTitle>
            <Button type="button" variant="ghost" size="icon" aria-label={`Supprimer le colis ${index + 1}`} onClick={() => packages.remove(index)}>
              <Trash2 className="size-4" />
            </Button>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-4">
              <SelectField form={form} name={`packages.${index}.packageTypeId`} label="Type" placeholder="—" options={toOptions(options.packageTypes)} />
              <SelectField form={form} name={`packages.${index}.groupingTypeId`} label="Regroupement" placeholder="—" options={toOptions(options.groupingTypes)} />
              <Field form={form} name={`packages.${index}.reference`} label="Référence" />
              <Field form={form} name={`packages.${index}.weight`} label="Poids (kg)" inputMode="decimal" />
            </div>
            <PackageLines form={form} index={index} lineOptions={lineOptions} />
          </CardContent>
        </Card>
      ))}
      <Button type="button" variant="outline" onClick={() => packages.append(emptyPackage())}>
        <Plus className="size-4" /> Ajouter un colis
      </Button>
    </div>
  )
}

function PackageLines({ form, index, lineOptions }: { form: UseFormReturn<OrderFormValues>; index: number; lineOptions: { value: string; label: string }[] }) {
  const allocations = useFieldArray({ control: form.control, name: `packages.${index}.lines`, keyName: 'fieldKey' })

  return (
    <div className="space-y-2">
      <p className="text-sm font-medium">Contenu</p>
      {allocations.fields.map((allocation, position) => (
        <div key={allocation.fieldKey} className="grid gap-3 sm:grid-cols-[1fr_120px_auto] sm:items-end">
          <SelectField form={form} name={`packages.${index}.lines.${position}.lineUid`} label="Ligne" placeholder="Choisir…" options={lineOptions} />
          <Field form={form} name={`packages.${index}.lines.${position}.quantity`} label="Quantité" inputMode="decimal" />
          <Button type="button" variant="ghost" size="icon" aria-label="Retirer du colis" onClick={() => allocations.remove(position)}>
            <Trash2 className="size-4" />
          </Button>
        </div>
      ))}
      <Button type="button" variant="link" className="px-0" onClick={() => allocations.append({ lineUid: '', quantity: '1' })}>
        <Plus className="size-4" /> Mettre une ligne dans ce colis
      </Button>
    </div>
  )
}
