import { z } from 'zod'

import type { OrganizationPayload } from '../api/organizations.api'

/** Longueurs reprises de `StoreOrganizationRequest`. */
export const organizationSchema = z.object({
  code: z.string().min(1, 'validation.required').max(64, 'validation.max'),
  name: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  legalName: z.string().max(255, 'validation.max'),
  registrationNumber: z.string().max(255, 'validation.max'),
  taxNumber: z.string().max(255, 'validation.max'),
  email: z.string().max(255, 'validation.max').email('validation.email').or(z.literal('')),
  phone: z.string().max(255, 'validation.max'),
  preferredLanguage: z.string().max(10, 'validation.max'),
  timezone: z.string().max(64, 'validation.max'),
  currencyCode: z
    .string()
    .refine((value) => value === '' || value.length === 3, 'validation.currency'),
  status: z.string().min(1, 'validation.required'),
})

export type OrganizationFormValues = z.infer<typeof organizationSchema>

export const ORGANIZATION_FORM_DEFAULTS: OrganizationFormValues = {
  code: '',
  name: '',
  legalName: '',
  registrationNumber: '',
  taxNumber: '',
  email: '',
  phone: '',
  preferredLanguage: 'fr',
  timezone: 'Europe/Paris',
  currencyCode: 'EUR',
  status: 'active',
}

const blank = (value: string): string | null => (value.trim() === '' ? null : value.trim())

/** Un champ laissé vide vaut `null`, jamais la chaîne vide. */
export function toOrganizationPayload(values: OrganizationFormValues): OrganizationPayload {
  return {
    code: values.code.trim(),
    name: values.name.trim(),
    legalName: blank(values.legalName),
    registrationNumber: blank(values.registrationNumber),
    taxNumber: blank(values.taxNumber),
    email: blank(values.email),
    phone: blank(values.phone),
    preferredLanguage: values.preferredLanguage.trim(),
    timezone: values.timezone.trim(),
    currencyCode: values.currencyCode.trim().toUpperCase(),
    status: values.status,
  }
}

export function toOrganizationFormValues(organization: {
  code: string
  name: string
  legalName: string | null
  registrationNumber: string | null
  taxNumber: string | null
  email: string | null
  phone: string | null
  preferredLanguage: string | null
  timezone: string | null
  currencyCode: string | null
  status: string
}): OrganizationFormValues {
  return {
    code: organization.code,
    name: organization.name,
    legalName: organization.legalName ?? '',
    registrationNumber: organization.registrationNumber ?? '',
    taxNumber: organization.taxNumber ?? '',
    email: organization.email ?? '',
    phone: organization.phone ?? '',
    preferredLanguage: organization.preferredLanguage ?? 'fr',
    timezone: organization.timezone ?? 'Europe/Paris',
    currencyCode: organization.currencyCode ?? 'EUR',
    status: organization.status,
  }
}
