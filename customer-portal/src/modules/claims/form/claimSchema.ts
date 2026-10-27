import { z } from 'zod'

import type { ClaimPayload } from '../types'

/**
 * Ce que le client décrit d'une réclamation (§5). La commande est
 * facultative — comme dans le diagramme — mais un service se désigne toujours
 * avec sa commande. Le backend revérifie tout.
 */
export const claimSchema = z
  .object({
    orderId: z.string(),
    orderServiceId: z.string(),
    claimType: z.string().trim().min(1, 'Indiquez le type de réclamation.').max(64, '64 caractères au plus.'),
    title: z.string().trim().min(1, 'Donnez un titre à la réclamation.').max(255, '255 caractères au plus.'),
    description: z.string().trim().max(5000, '5 000 caractères au plus.'),
  })
  .refine((values) => values.orderServiceId === '' || values.orderId !== '', {
    message: 'Choisissez la commande du service concerné.',
    path: ['orderId'],
  })

export type ClaimFormValues = z.infer<typeof claimSchema>

export const CLAIM_FIELDS = ['orderId', 'orderServiceId', 'claimType', 'title', 'description'] as const

export function toClaimPayload(values: ClaimFormValues): ClaimPayload {
  return {
    orderId: values.orderId || null,
    orderServiceId: values.orderServiceId || null,
    claimType: values.claimType,
    title: values.title,
    description: values.description || null,
  }
}
