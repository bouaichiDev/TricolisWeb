import { z } from 'zod'

import type {
  CustomerApiConfiguration,
  CustomerApiConfigurationPayload,
  CustomerImportConfiguration,
  CustomerImportConfigurationPayload,
} from '../types/customerIntegration'

/**
 * Longueurs reprises de `StoreImportConfigurationRequest`.
 *
 * `sourceType` et `fileFormat` restent des chaînes libres : aucune énumération,
 * aucune table, aucune constante ne les contraint côté serveur. Les §8 et §9
 * interdisent d'en inventer une liste ici.
 *
 * `mapping` et `validationRules` sont saisis en texte JSON et validés à part,
 * par `JsonConfigurationEditor` : Zod ne peut pas dire à quelle position une
 * accolade manque, `JSON.parse` le peut.
 */
export const customerImportConfigurationSchema = z.object({
  customerId: z.string().min(1, 'validation.required'),
  name: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  sourceType: z.string().min(1, 'validation.required').max(64, 'validation.max'),
  fileFormat: z.string().min(1, 'validation.required').max(32, 'validation.max'),
  mapping: z.string(),
  validationRules: z.string(),
  isActive: z.boolean(),
})

export type CustomerImportConfigurationFormValues = z.infer<
  typeof customerImportConfigurationSchema
>

export const IMPORT_CONFIGURATION_DEFAULTS: CustomerImportConfigurationFormValues = {
  customerId: '',
  name: '',
  sourceType: '',
  fileFormat: '',
  mapping: '',
  validationRules: '',
  isActive: true,
}

export function toImportConfigurationFormValues(
  configuration: CustomerImportConfiguration,
): CustomerImportConfigurationFormValues {
  const text = (value: Record<string, unknown> | null) =>
    value === null ? '' : JSON.stringify(value, null, 2)

  return {
    customerId: configuration.customerId,
    name: configuration.name,
    sourceType: configuration.sourceType,
    fileFormat: configuration.fileFormat,
    mapping: text(configuration.mapping),
    validationRules: text(configuration.validationRules),
    isActive: configuration.isActive,
  }
}

/**
 * Accès API client.
 *
 * `allowedIps` et `permissions` sont des tableaux tenus par des éditeurs
 * dédiés : le backend valide chaque IP par la règle `IpOrCidr`, et chaque
 * permission contre les codes RBAC existants. La forme est donc connue — le §26
 * et le §27 autorisent alors mieux qu'un éditeur JSON brut.
 *
 * **Ni `apiKeyHash`, ni clé.** Le formulaire ne les demande jamais : la clé est
 * générée par le serveur et n'entre pas dans une saisie.
 */
export const customerApiConfigurationSchema = z.object({
  customerId: z.string().min(1, 'validation.required'),
  name: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  allowedIps: z.array(z.string()).max(100, 'validation.max'),
  permissions: z.array(z.string()).max(200, 'validation.max'),
  isActive: z.boolean(),
})

export type CustomerApiConfigurationFormValues = z.infer<
  typeof customerApiConfigurationSchema
>

export const API_CONFIGURATION_DEFAULTS: CustomerApiConfigurationFormValues = {
  customerId: '',
  name: '',
  allowedIps: [],
  permissions: [],
  isActive: true,
}

export function toApiConfigurationFormValues(
  configuration: CustomerApiConfiguration,
): CustomerApiConfigurationFormValues {
  return {
    customerId: configuration.customerId,
    name: configuration.name,
    allowedIps: configuration.allowedIps ?? [],
    permissions: configuration.permissions ?? [],
    isActive: configuration.isActive,
  }
}

/**
 * Une liste vide part en `null`, pas en `[]`.
 *
 * Les deux champs sont `nullable` côté serveur, et la distinction porte du
 * sens : aucune restriction d'IP n'est la même chose qu'une liste vide, qui se
 * lirait « aucune IP autorisée ».
 */
const listOrNull = (values: string[]): string[] | null => (values.length === 0 ? null : values)

export function toApiConfigurationPayload(
  values: CustomerApiConfigurationFormValues,
): CustomerApiConfigurationPayload {
  return {
    customerId: values.customerId,
    name: values.name.trim(),
    allowedIps: listOrNull(values.allowedIps),
    permissions: listOrNull(values.permissions),
    isActive: values.isActive,
  }
}

export function toImportConfigurationPayload(
  values: CustomerImportConfigurationFormValues,
  mapping: Record<string, unknown> | null,
  validationRules: Record<string, unknown> | null,
): CustomerImportConfigurationPayload {
  return {
    customerId: values.customerId,
    name: values.name.trim(),
    sourceType: values.sourceType.trim(),
    fileFormat: values.fileFormat.trim(),
    mapping,
    validationRules,
    isActive: values.isActive,
  }
}
