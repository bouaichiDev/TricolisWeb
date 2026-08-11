import { z } from 'zod'

/**
 * Validation du formulaire client.
 *
 * Le §30 borne le role du frontend : requis, format, longueur. Les regles
 * metier — unicite du code dans l'organisation, transitions de statut —
 * restent au backend, qui reste seul a pouvoir les verifier.
 *
 * Les longueurs reproduisent celles de `StoreCustomerRequest` : les depasser
 * cote client evite un aller-retour pour un refus certain.
 */
export const customerSchema = z.object({
  code: z
    .string()
    .min(1, 'validation.required')
    .max(64, 'validation.max')
    .regex(/^[A-Za-z0-9._-]+$/, 'validation.code'),
  name: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  legalName: z.string().max(255).optional().or(z.literal('')),
  email: z.string().email('validation.email').max(255).optional().or(z.literal('')),
  phone: z.string().max(255).optional().or(z.literal('')),
  paymentMode: z.string().max(64).optional().or(z.literal('')),
  communicationMode: z.string().max(64).optional().or(z.literal('')),
  catalogEnabled: z.boolean(),
  stockEnabled: z.boolean(),
  packageEnabled: z.boolean(),
  appointmentEnabled: z.boolean(),
  trackingEnabled: z.boolean(),
  status: z.string().min(1, 'validation.required'),
})

export type CustomerFormValues = z.infer<typeof customerSchema>

export const CUSTOMER_FORM_DEFAULTS: CustomerFormValues = {
  code: '',
  name: '',
  legalName: '',
  email: '',
  phone: '',
  paymentMode: '',
  communicationMode: '',
  catalogEnabled: false,
  stockEnabled: false,
  packageEnabled: false,
  appointmentEnabled: false,
  trackingEnabled: false,
  status: 'active',
}

/** Les chaines vides deviennent `null` : l'API distingue « vide » de « absent ». */
export function toCustomerPayload(values: CustomerFormValues) {
  const blankToNull = (value: string | undefined) =>
    value === undefined || value.trim() === '' ? null : value

  return {
    ...values,
    legalName: blankToNull(values.legalName),
    email: blankToNull(values.email),
    phone: blankToNull(values.phone),
    paymentMode: blankToNull(values.paymentMode),
    communicationMode: blankToNull(values.communicationMode),
  }
}
