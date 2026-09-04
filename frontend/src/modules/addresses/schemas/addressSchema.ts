import { z } from 'zod'

import type { AddressPayload } from '../api/addresses.api'

/** Longueurs et bornes reprises de `StoreAddressRequest`. */
export const addressSchema = z.object({
  name: z.string().max(255, 'validation.max'),
  addressLine1: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  addressLine2: z.string().max(255, 'validation.max'),
  addressNumber: z.string().max(64, 'validation.max'),
  route: z.string().max(255, 'validation.max'),
  postalCode: z.string().max(64, 'validation.max'),
  city: z.string().max(255, 'validation.max'),
  country: z
    .string()
    .refine((value) => value === '' || value.length === 2, 'validation.country'),
  instructions: z.string(),
  timeWindowFrom: z.string(),
  timeWindowTo: z.string(),
})

export type AddressFormValues = z.infer<typeof addressSchema>

export const ADDRESS_FORM_DEFAULTS: AddressFormValues = {
  name: '',
  addressLine1: '',
  addressLine2: '',
  addressNumber: '',
  route: '',
  postalCode: '',
  city: '',
  country: '',
  instructions: '',
  timeWindowFrom: '',
  timeWindowTo: '',
}

const blankToNull = (value: string): string | null => (value.trim() === '' ? null : value.trim())

/**
 * Un champ vide vaut « non renseigne », pas « chaine vide ».
 *
 * Les colonnes sont `nullable` cote base ; envoyer `''` y ecrirait une valeur
 * qui n'en est pas une et fausserait les recherches.
 */
export function toAddressPayload(values: AddressFormValues): AddressPayload {
  return {
    name: blankToNull(values.name),
    addressLine1: values.addressLine1.trim(),
    addressLine2: blankToNull(values.addressLine2),
    addressNumber: blankToNull(values.addressNumber),
    route: blankToNull(values.route),
    postalCode: blankToNull(values.postalCode),
    city: blankToNull(values.city),
    country: blankToNull(values.country)?.toUpperCase() ?? null,
    instructions: blankToNull(values.instructions),
    timeWindowFrom: blankToNull(values.timeWindowFrom),
    timeWindowTo: blankToNull(values.timeWindowTo),
  }
}

export function toAddressFormValues(address: {
  name: string | null
  addressLine1: string
  addressLine2: string | null
  addressNumber: string | null
  route: string | null
  postalCode: string | null
  city: string | null
  country: string | null
  instructions: string | null
  timeWindowFrom: string | null
  timeWindowTo: string | null
}): AddressFormValues {
  return {
    name: address.name ?? '',
    addressLine1: address.addressLine1,
    addressLine2: address.addressLine2 ?? '',
    addressNumber: address.addressNumber ?? '',
    route: address.route ?? '',
    postalCode: address.postalCode ?? '',
    city: address.city ?? '',
    country: address.country ?? '',
    instructions: address.instructions ?? '',
    timeWindowFrom: address.timeWindowFrom?.slice(0, 5) ?? '',
    timeWindowTo: address.timeWindowTo?.slice(0, 5) ?? '',
  }
}
