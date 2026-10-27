import type { UseFormReturn } from 'react-hook-form'

import { Field } from '@/shared/components/form/Field'

import type { OrderFormValues } from '../form/orderSchema'

/**
 * Un destinataire ponctuel : son adresse et son nom deviennent l'adresse et
 * le contact principal du service, sans entrer dans votre carnet d'adresses.
 */
export function RecipientFields({ form, index }: { form: UseFormReturn<OrderFormValues>; index: number }) {
  const path = `services.${index}.recipient` as const

  return (
    <div className="grid gap-3 sm:grid-cols-2">
      <Field form={form} name={`${path}.firstName`} label="Prénom du destinataire" />
      <Field form={form} name={`${path}.lastName`} label="Nom du destinataire" />
      <Field form={form} name={`${path}.company`} label="Société (facultatif)" />
      <Field form={form} name={`${path}.phone`} label="Téléphone" type="tel" />
      <div className="sm:col-span-2">
        <Field form={form} name={`${path}.addressLine1`} label="Adresse" />
      </div>
      <Field form={form} name={`${path}.postalCode`} label="NPA / Code postal" />
      <Field form={form} name={`${path}.city`} label="Localité" />
      <Field form={form} name={`${path}.country`} label="Pays (code ISO)" maxLength={2} />
    </div>
  )
}
