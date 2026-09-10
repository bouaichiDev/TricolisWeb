import { z } from 'zod'

import { hasSubject } from '@/modules/communications/types/communication'
import type { TemplatePayload } from '../api/templates.api'
import { isDocumentType, type Template } from '../types/template'
import {
  DELIVERY_NOTE_STARTER_BODY,
  DELIVERY_NOTE_STARTER_VARIABLES,
} from '../utils/deliveryNoteStarter'

/**
 * Longueurs et motif repris de `StoreTemplateRequest`.
 *
 * Les règles sont recopiées pour dire *avant* l'envoi ce qui sera refusé, pas
 * pour décider à la place du serveur — lui seul tranche.
 */
export const templateSchema = z
  .object({
    customerId: z.string(),
    serviceId: z.string(),
    code: z
      .string()
      .min(1, 'validation.required')
      .max(64, 'validation.max')
      .regex(/^[A-Za-z0-9._-]+$/, 'validation.code'),
    name: z.string().min(1, 'validation.required').max(255, 'validation.max'),
    channel: z.string(),
    templateType: z.string().min(1, 'validation.required'),
    language: z.string().min(1, 'validation.required').max(10, 'validation.max'),
    subjectTemplate: z.string().max(65535, 'validation.max'),
    bodyTemplate: z.string().min(1, 'validation.required'),
    bodyFormat: z.string(),
    availableVariables: z.array(z.string()),
    isActive: z.boolean(),
    isDefault: z.boolean(),
  })
  .superRefine((values, context) => {
    // Un document n'a ni canal ni objet ; un message doit dire par ou il part.
    // `TemplateNature` refuse exactement ces trois cas cote serveur.
    if (isDocumentType(values.templateType)) {
      if (values.channel !== '') {
        context.addIssue({
          code: 'custom',
          path: ['channel'],
          message: 'templates.errors.documentChannel',
        })
      }

      return
    }

    if (values.channel === '') {
      context.addIssue({ code: 'custom', path: ['channel'], message: 'validation.required' })

      return
    }

    if (hasSubject(values.channel) && values.subjectTemplate.trim() === '') {
      context.addIssue({ code: 'custom', path: ['subjectTemplate'], message: 'validation.required' })
    }
  })

export type TemplateFormValues = z.infer<typeof templateSchema>

export const TEMPLATE_FORM_DEFAULTS: TemplateFormValues = {
  customerId: '',
  serviceId: '',
  code: '',
  name: '',
  channel: 'email',
  templateType: 'custom',
  language: 'fr',
  subjectTemplate: '',
  bodyTemplate: '',
  bodyFormat: 'text',
  availableVariables: [],
  isActive: true,
  isDefault: false,
}

/** Valeurs de départ d'un document : ni canal, ni objet, et du HTML. */
const DOCUMENT_FORM_DEFAULTS: TemplateFormValues = {
  ...TEMPLATE_FORM_DEFAULTS,
  channel: '',
  subjectTemplate: '',
  bodyFormat: 'html',
}

/** Valeurs de départ d'un modèle de facture : un document, jamais un message. */
export const INVOICE_FORM_DEFAULTS: TemplateFormValues = {
  ...DOCUMENT_FORM_DEFAULTS,
  templateType: 'invoice',
}

/**
 * Valeurs de départ d'un modèle de BL.
 *
 * Le corps et les variables sont pré-remplis, contrairement à la facture : un
 * BL a huit blocs — en-tête, donneur d'ordre, chargement, livraison, deux
 * tableaux, observations, signature — et personne ne les écrit de mémoire sans
 * se tromper d'un chemin.
 */
export const DELIVERY_NOTE_FORM_DEFAULTS: TemplateFormValues = {
  ...DOCUMENT_FORM_DEFAULTS,
  templateType: 'delivery_note',
  bodyTemplate: DELIVERY_NOTE_STARTER_BODY,
  availableVariables: DELIVERY_NOTE_STARTER_VARIABLES,
}

/** Les valeurs de départ d'une nature donnée. */
export function defaultsFor(templateType: string | undefined): TemplateFormValues {
  if (templateType === 'invoice') return INVOICE_FORM_DEFAULTS
  if (templateType === 'delivery_note') return DELIVERY_NOTE_FORM_DEFAULTS

  return TEMPLATE_FORM_DEFAULTS
}

export function isTemplateComplete(values: TemplateFormValues): boolean {
  return templateSchema.safeParse(values).success
}

const blankToNull = (value: string) => (value.trim() === '' ? null : value.trim())

export function toTemplatePayload(values: TemplateFormValues): TemplatePayload {
  const document = isDocumentType(values.templateType)

  return {
    customerId: blankToNull(values.customerId),
    // Seule la facture perd son service : le serveur le force a nul pour elle.
    // Un BL le garde — il decrit une prestation precise.
    serviceId: values.templateType === 'invoice' ? null : blankToNull(values.serviceId),
    code: values.code.trim(),
    name: values.name.trim(),
    // Un document part sans canal, et sans objet : garder la saisie d'un
    // ancien type enverrait ce que le serveur refuse.
    channel: document ? null : blankToNull(values.channel),
    templateType: values.templateType,
    subjectTemplate:
      document || !hasSubject(values.channel) ? null : blankToNull(values.subjectTemplate),
    bodyTemplate: values.bodyTemplate,
    bodyFormat: values.bodyFormat,
    language: values.language.trim(),
    availableVariables: values.availableVariables,
    isActive: values.isActive,
    isDefault: values.isDefault,
  }
}

export function toTemplateFormValues(template: Template): TemplateFormValues {
  return {
    customerId: template.customerId ?? '',
    serviceId: template.serviceId ?? '',
    code: template.code,
    name: template.name,
    channel: template.channel ?? '',
    templateType: template.templateType,
    language: template.language,
    subjectTemplate: template.subjectTemplate ?? '',
    bodyTemplate: template.bodyTemplate,
    bodyFormat: template.bodyFormat ?? 'text',
    availableVariables: template.availableVariables ?? [],
    isActive: template.isActive,
    isDefault: template.isDefault,
  }
}

/**
 * Les valeurs d'une **duplication**.
 *
 * Tout est repris sauf deux choses : le `code`, qui identifie le modèle et doit
 * rester unique, et `isDefault` — deux modèles par défaut pour le même client
 * feraient dépendre le choix de l'ordre alphabétique, ce qu'aucun utilisateur
 * ne devine.
 */
export function toDuplicateFormValues(template: Template): TemplateFormValues {
  return {
    ...toTemplateFormValues(template),
    code: `${template.code}_COPIE`.slice(0, 64),
    name: `${template.name} (copie)`.slice(0, 255),
    isDefault: false,
  }
}
