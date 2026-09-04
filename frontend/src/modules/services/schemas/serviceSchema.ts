import { z } from 'zod'

import type { ServicePayload } from '../api/services.api'

/**
 * Longueurs et obligations reprises de `StoreServiceRequest`.
 *
 * `unit` et `defaultDurationMinutes` sont `required` côté API : les rendre
 * facultatifs ici produirait un formulaire acceptant ce que le serveur refuse.
 */
export const serviceSchema = z.object({
  code: z.string().min(1, 'validation.required').max(64, 'validation.max'),
  name: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  unit: z.string().min(1, 'validation.required').max(32, 'validation.max'),
  defaultDurationMinutes: z.coerce
    .number({ message: 'validation.number' })
    .int('validation.integer')
    .min(0, 'validation.min'),
  billableToCustomer: z.boolean(),
  payableToProvider: z.boolean(),
  requiresAddress: z.boolean(),
  requiresContact: z.boolean(),
  status: z.string().min(1, 'validation.required'),
})

export type ServiceFormValues = z.infer<typeof serviceSchema>

export const SERVICE_FORM_DEFAULTS: ServiceFormValues = {
  code: '',
  name: '',
  unit: '',
  defaultDurationMinutes: 0,
  billableToCustomer: true,
  payableToProvider: false,
  requiresAddress: true,
  requiresContact: false,
  status: 'active',
}

export function toServicePayload(values: ServiceFormValues): ServicePayload {
  return {
    code: values.code.trim(),
    name: values.name.trim(),
    unit: values.unit.trim(),
    defaultDurationMinutes: values.defaultDurationMinutes,
    billableToCustomer: values.billableToCustomer,
    payableToProvider: values.payableToProvider,
    requiresAddress: values.requiresAddress,
    requiresContact: values.requiresContact,
    status: values.status,
  }
}
