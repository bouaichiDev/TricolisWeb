import { Plus, Trash2 } from 'lucide-react'
import { useFieldArray, type UseFormReturn } from 'react-hook-form'

import { Field } from '@/shared/components/form/Field'
import { Button } from '@/shared/components/ui/button'

import type { OrderFormValues } from '../form/orderSchema'

/** Les personnes à joindre pour ce service ; la première est le contact principal. */
export function ContactsEditor({ form, index }: { form: UseFormReturn<OrderFormValues>; index: number }) {
  const contacts = useFieldArray({ control: form.control, name: `services.${index}.contacts`, keyName: 'fieldKey' })

  return (
    <div className="space-y-2">
      <p className="text-sm font-medium">Contacts sur place</p>
      {contacts.fields.map((contact, position) => (
        <div key={contact.fieldKey} className="grid gap-3 sm:grid-cols-[1fr_1fr_1fr_1fr_auto] sm:items-end">
          <Field form={form} name={`services.${index}.contacts.${position}.firstName`} label="Prénom" />
          <Field form={form} name={`services.${index}.contacts.${position}.lastName`} label="Nom" />
          <Field form={form} name={`services.${index}.contacts.${position}.phone`} label="Téléphone" type="tel" />
          <Field form={form} name={`services.${index}.contacts.${position}.email`} label="E-mail" type="email" />
          <Button type="button" variant="ghost" size="icon" aria-label="Retirer le contact" onClick={() => contacts.remove(position)}>
            <Trash2 className="size-4" />
          </Button>
        </div>
      ))}
      <Button
        type="button"
        variant="link"
        className="px-0"
        disabled={contacts.fields.length >= 5}
        onClick={() => contacts.append({ firstName: '', lastName: '', phone: '', email: '' })}
      >
        <Plus className="size-4" /> Ajouter un contact
      </Button>
    </div>
  )
}
