import { z } from 'zod'

/**
 * Le formulaire de commande, étape par étape.
 *
 * Les lignes et les colis portent un `uid` local stable : supprimer une
 * ligne ne doit pas faire glisser les affectations des colis sur la voisine.
 * Il n'est traduit en index (`lineKey`) qu'à l'envoi (`toPayload`).
 */
const decimal = (message: string) =>
  z.string().trim().min(1, message).refine((value) => Number(value.replace(',', '.')) > 0, message)

const optionalNumber = z
  .string()
  .trim()
  .refine((value) => value === '' || Number(value.replace(',', '.')) >= 0, 'Nombre positif attendu.')

export const lineSchema = z
  .object({
    uid: z.string(),
    catalogItemId: z.string(),
    articleLabel: z.string(),
    name: z.string().trim().max(255),
    quantity: decimal('Quantité supérieure à zéro.'),
    weight: optionalNumber,
  })
  .refine((line) => line.catalogItemId !== '' || line.name !== '', {
    message: 'Choisissez un article ou saisissez un libellé.',
    path: ['name'],
  })

export const packageSchema = z.object({
  uid: z.string(),
  packageTypeId: z.string(),
  groupingTypeId: z.string(),
  reference: z.string().trim().max(255),
  weight: optionalNumber,
  lines: z.array(z.object({ lineUid: z.string().min(1, 'Choisissez une ligne.'), quantity: decimal('Quantité supérieure à zéro.') })),
})

export const contactSchema = z.object({
  firstName: z.string().trim().min(1, 'Prénom requis.').max(255),
  lastName: z.string().trim().max(255),
  phone: z.string().trim().max(64),
  email: z.union([z.literal(''), z.string().trim().email('Adresse e-mail invalide.')]),
})

export const recipientSchema = z.object({
  firstName: z.string().trim(),
  lastName: z.string().trim(),
  company: z.string().trim(),
  addressLine1: z.string().trim(),
  postalCode: z.string().trim(),
  city: z.string().trim(),
  country: z.string().trim(),
  phone: z.string().trim(),
})

export const serviceSchema = z
  .object({
    serviceId: z.string().min(1, 'Choisissez un service.'),
    addressMode: z.enum(['book', 'new']),
    addressId: z.string(),
    recipient: recipientSchema,
    requestedDate: z.string().min(1, 'Date requise.'),
    requestedFrom: z.string(),
    requestedTo: z.string(),
    instructions: z.string().trim().max(2000),
    contacts: z.array(contactSchema).max(5),
    packageUids: z.array(z.string()),
  })
  .superRefine((service, context) => {
    if (service.addressMode === 'book' && service.addressId === '') {
      context.addIssue({ code: 'custom', message: 'Choisissez une adresse.', path: ['addressId'] })
    }

    if (service.addressMode === 'new') {
      const required = ['firstName', 'lastName', 'addressLine1', 'postalCode', 'city'] as const
      required.forEach((field) => {
        if (service.recipient[field] === '') context.addIssue({ code: 'custom', message: 'Champ requis.', path: ['recipient', field] })
      })
      if (service.recipient.country.length !== 2) {
        context.addIssue({ code: 'custom', message: 'Code pays à 2 lettres (CH, FR…).', path: ['recipient', 'country'] })
      }
    }

    if (service.requestedFrom !== '' && service.requestedTo !== '' && service.requestedTo <= service.requestedFrom) {
      context.addIssue({ code: 'custom', message: 'La fin du créneau doit suivre son début.', path: ['requestedTo'] })
    }
  })

export const orderSchema = z.object({
  customerReference: z.string().trim().max(255),
  externalReference: z.string().trim().max(255),
  workerRemark: z.string().trim().max(2000),
  lines: z.array(lineSchema).min(1, 'Ajoutez au moins une ligne.'),
  packages: z.array(packageSchema),
  services: z.array(serviceSchema).min(1, 'Ajoutez au moins un service.').max(20),
})

export type OrderFormValues = z.infer<typeof orderSchema>
export type LineValues = z.infer<typeof lineSchema>
export type PackageValues = z.infer<typeof packageSchema>
export type ServiceValues = z.infer<typeof serviceSchema>

/** Corps envoyé au backend (`StoreCustomerOrderRequest`). */
export interface OrderPayload {
  customerReference: string | null
  externalReference: string | null
  workerRemark: string | null
  lines: Record<string, unknown>[]
  packages: Record<string, unknown>[]
  services: Record<string, unknown>[]
}
