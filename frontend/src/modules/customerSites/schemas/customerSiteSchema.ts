import { z } from 'zod'

import {
  ADDRESS_FORM_DEFAULTS,
  addressSchema,
} from '@/modules/addresses/schemas/addressSchema'

/**
 * Site client = identite du site + adresse.
 *
 * Les deux sont saisis ensemble parce qu'un site sans adresse n'a pas de sens
 * metier, et parce que l'API exige un `addressId` a la creation. Le schema les
 * fusionne pour n'avoir qu'une seule validation a passer.
 */
export const customerSiteSchema = addressSchema.extend({
  code: z.string().min(1, 'validation.required').max(64, 'validation.max'),
  siteName: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  siteType: z.string().max(64, 'validation.max'),
  isDefault: z.boolean(),
  status: z.string().min(1, 'validation.required'),
})

export type CustomerSiteFormValues = z.infer<typeof customerSiteSchema>

export const CUSTOMER_SITE_FORM_DEFAULTS: CustomerSiteFormValues = {
  ...ADDRESS_FORM_DEFAULTS,
  code: '',
  siteName: '',
  siteType: '',
  isDefault: false,
  status: 'active',
}
