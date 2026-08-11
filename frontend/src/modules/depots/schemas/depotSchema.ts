import { z } from 'zod'

/** Longueurs reprises de `StoreDepotRequest`. */
export const depotSchema = z.object({
  code: z
    .string()
    .min(1, 'validation.required')
    .max(64, 'validation.max')
    .regex(/^[A-Za-z0-9._-]+$/, 'validation.code'),
  name: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  status: z.string().min(1, 'validation.required'),
})

export type DepotFormValues = z.infer<typeof depotSchema>

export const DEPOT_FORM_DEFAULTS: DepotFormValues = { code: '', name: '', status: 'active' }
