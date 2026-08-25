import type { CommunicationTemplatePayload } from '../api/communication-templates.api'
import { hasSubject, type CommunicationTemplate } from '../types/communication'

/** Valeurs du formulaire de modèle. */
export interface TemplateFormValues {
  code: string
  name: string
  channel: string
  templateType: string
  language: string
  subjectTemplate: string
  bodyTemplate: string
  bodyFormat: string
  availableVariables: string[]
  isActive: boolean
  isDefault: boolean
}

export const TEMPLATE_FORM_DEFAULTS: TemplateFormValues = {
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

/**
 * Le code est contraint côté serveur : `regex:/^[A-Za-z0-9._-]+$/`.
 *
 * La règle est recopiée pour dire *avant* l'envoi ce qui sera refusé, pas pour
 * décider à la place du serveur — lui seul tranche.
 */
export const CODE_PATTERN = /^[A-Za-z0-9._-]+$/

export function isTemplateComplete(values: TemplateFormValues): boolean {
  if (values.code.trim() === '' || !CODE_PATTERN.test(values.code.trim())) return false
  if (values.name.trim() === '' || values.bodyTemplate.trim() === '') return false
  if (values.language.trim() === '') return false

  // Le sujet n'est exige que sur les canaux qui en ont un.
  return !hasSubject(values.channel) || values.subjectTemplate.trim() !== ''
}

export function toTemplatePayload(values: TemplateFormValues): CommunicationTemplatePayload {
  return {
    code: values.code.trim(),
    name: values.name.trim(),
    channel: values.channel,
    templateType: values.templateType,
    language: values.language.trim(),
    // Un canal sans sujet en envoie `null` : garder la saisie d'un ancien canal
    // enverrait un sujet que le message n'utilisera pas.
    subjectTemplate: hasSubject(values.channel) ? values.subjectTemplate.trim() : null,
    bodyTemplate: values.bodyTemplate,
    bodyFormat: values.bodyFormat,
    availableVariables: values.availableVariables,
    isActive: values.isActive,
    isDefault: values.isDefault,
  }
}

export function toTemplateFormValues(template: CommunicationTemplate): TemplateFormValues {
  return {
    code: template.code,
    name: template.name,
    channel: template.channel,
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
