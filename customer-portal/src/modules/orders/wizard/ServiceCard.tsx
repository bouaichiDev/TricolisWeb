import { Trash2 } from 'lucide-react'
import { useId } from 'react'
import { useWatch, type UseFormReturn } from 'react-hook-form'

import { Field } from '@/shared/components/form/Field'
import { SelectField } from '@/shared/components/form/SelectField'
import { Button } from '@/shared/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/shared/components/ui/card'
import { Checkbox } from '@/shared/components/ui/checkbox'
import { Label } from '@/shared/components/ui/label'

import type { OrderFormValues } from '../form/orderSchema'
import type { FormOptions } from '../types'
import { ContactsEditor } from './ContactsEditor'
import { RecipientFields } from './RecipientFields'

interface ServiceCardProps {
  form: UseFormReturn<OrderFormValues>
  index: number
  options: FormOptions
  onRemove?: () => void
}

/** Un service : quoi, où, quand, qui joindre, et quels colis il concerne. */
export function ServiceCard({ form, index, options, onRemove }: ServiceCardProps) {
  const modeId = useId()
  const mode = useWatch({ control: form.control, name: `services.${index}.addressMode` })
  const packages = useWatch({ control: form.control, name: 'packages' }) ?? []
  const linked = useWatch({ control: form.control, name: `services.${index}.packageUids` }) ?? []

  const toggle = (uid: string, checked: boolean) =>
    form.setValue(`services.${index}.packageUids`, checked ? [...linked, uid] : linked.filter((value) => value !== uid))

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between space-y-0">
        <CardTitle className="text-base">Service {index + 1}</CardTitle>
        {onRemove && (
          <Button type="button" variant="ghost" size="icon" aria-label={`Supprimer le service ${index + 1}`} onClick={onRemove}>
            <Trash2 className="size-4" />
          </Button>
        )}
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid gap-3 sm:grid-cols-4">
          <SelectField form={form} name={`services.${index}.serviceId`} label="Service" placeholder="Choisir…"
            options={options.services.map((service) => ({ value: service.id, label: service.name }))} />
          <Field form={form} name={`services.${index}.requestedDate`} label="Date souhaitée" type="date" />
          <Field form={form} name={`services.${index}.requestedFrom`} label="De" type="time" />
          <Field form={form} name={`services.${index}.requestedTo`} label="À" type="time" />
        </div>

        <fieldset className="space-y-3">
          <legend className="text-sm font-medium">Adresse</legend>
          <div className="flex gap-4 text-sm" id={modeId}>
            <label className="flex items-center gap-2">
              <input type="radio" value="book" {...form.register(`services.${index}.addressMode`)} /> Mon carnet d’adresses
            </label>
            <label className="flex items-center gap-2">
              <input type="radio" value="new" {...form.register(`services.${index}.addressMode`)} /> Nouveau destinataire
            </label>
          </div>
          {mode === 'new' ? (
            <RecipientFields form={form} index={index} />
          ) : (
            <SelectField form={form} name={`services.${index}.addressId`} label="Adresse" placeholder="Choisir…"
              options={options.addresses.map((address) => ({
                value: address.id,
                label: [address.name, address.addressLine1, `${address.postalCode ?? ''} ${address.city ?? ''}`.trim()].filter(Boolean).join(' — '),
              }))} />
          )}
        </fieldset>

        <Field form={form} name={`services.${index}.instructions`} label="Instructions" />
        <ContactsEditor form={form} index={index} />

        {packages.length > 0 && (
          <div className="space-y-2">
            <p className="text-sm font-medium">Colis concernés</p>
            {packages.map((pkg, position) => (
              <div key={pkg.uid} className="flex items-center gap-2">
                <Checkbox id={`svc-${index}-pkg-${pkg.uid}`} checked={linked.includes(pkg.uid)}
                  onCheckedChange={(checked) => toggle(pkg.uid, checked === true)} />
                <Label htmlFor={`svc-${index}-pkg-${pkg.uid}`} className="font-normal">
                  Colis {position + 1}{pkg.reference ? ` — ${pkg.reference}` : ''}
                </Label>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
