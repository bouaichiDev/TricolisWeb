import { z } from 'zod'

/** Longueurs reprises de `StoreAgencyRequest` ; les regles metier restent au backend. */
export const agencySchema = z.object({
  code: z
    .string()
    .min(1, 'validation.required')
    .max(64, 'validation.max')
    .regex(/^[A-Za-z0-9._-]+$/, 'validation.code'),
  name: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  shortName: z.string().max(64).optional().or(z.literal('')),
  email: z.string().email('validation.email').max(255).optional().or(z.literal('')),
  phone: z.string().max(255).optional().or(z.literal('')),
  color: z.string().max(7).optional().or(z.literal('')),
  loadingPoint: z.string().max(255).optional().or(z.literal('')),
  status: z.string().min(1, 'validation.required'),
})

export type AgencyFormValues = z.infer<typeof agencySchema>

export const AGENCY_FORM_DEFAULTS: AgencyFormValues = {
  code: '',
  name: '',
  shortName: '',
  email: '',
  phone: '',
  color: '',
  loadingPoint: '',
  status: 'active',
}

const blankToNull = (value: string | undefined) =>
  value === undefined || value.trim() === '' ? null : value

export function toAgencyPayload(values: AgencyFormValues) {
  return {
    ...values,
    shortName: blankToNull(values.shortName),
    email: blankToNull(values.email),
    phone: blankToNull(values.phone),
    color: blankToNull(values.color),
    loadingPoint: blankToNull(values.loadingPoint),
  }
}
