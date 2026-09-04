import { z } from 'zod'

import type { CatalogItemPayload, CatalogPayload } from '../api/catalogs.api'

/** Longueurs reprises de `StoreCatalogRequest`. */
export const catalogSchema = z.object({
  code: z.string().min(1, 'validation.required').max(64, 'validation.max'),
  name: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  description: z.string(),
  status: z.string().min(1, 'validation.required'),
})

export type CatalogFormValues = z.infer<typeof catalogSchema>

export const CATALOG_FORM_DEFAULTS: CatalogFormValues = {
  code: '',
  name: '',
  description: '',
  status: 'active',
}

const blank = (value: string): string | null => (value.trim() === '' ? null : value.trim())

export function toCatalogPayload(values: CatalogFormValues): CatalogPayload {
  return {
    code: values.code.trim(),
    name: values.name.trim(),
    description: blank(values.description),
    status: values.status,
  }
}

/**
 * Longueurs reprises de `StoreCatalogItemRequest`.
 *
 * Les dimensions sont saisies en texte puis converties : un champ nombre vide
 * donne `NaN` avec `valueAsNumber`, et `NaN` passerait la validation `min(0)`
 * de Zod sans être un nombre.
 */
export const catalogItemSchema = z.object({
  articleCode: z.string().min(1, 'validation.required').max(128, 'validation.max'),
  barcode: z.string().max(128, 'validation.max'),
  name: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  description: z.string(),
  weight: z.string(),
  volume: z.string(),
  length: z.string(),
  width: z.string(),
  height: z.string(),
  assemblyTimeMinutes: z.string(),
  status: z.string().min(1, 'validation.required'),
})

export type CatalogItemFormValues = z.infer<typeof catalogItemSchema>

export const CATALOG_ITEM_FORM_DEFAULTS: CatalogItemFormValues = {
  articleCode: '',
  barcode: '',
  name: '',
  description: '',
  weight: '',
  volume: '',
  length: '',
  width: '',
  height: '',
  assemblyTimeMinutes: '',
  status: 'active',
}

/** Un champ vide reste absent de la charge utile : l'API a ses propres défauts. */
function optionalNumber(value: string): number | undefined {
  const trimmed = value.trim()
  if (trimmed === '') return undefined

  const parsed = Number(trimmed)

  return Number.isFinite(parsed) ? parsed : undefined
}

export function toCatalogItemPayload(values: CatalogItemFormValues): CatalogItemPayload {
  return {
    articleCode: values.articleCode.trim(),
    barcode: blank(values.barcode),
    name: values.name.trim(),
    description: blank(values.description),
    weight: optionalNumber(values.weight),
    volume: optionalNumber(values.volume),
    length: optionalNumber(values.length) ?? null,
    width: optionalNumber(values.width) ?? null,
    height: optionalNumber(values.height) ?? null,
    assemblyTimeMinutes: optionalNumber(values.assemblyTimeMinutes) ?? null,
    status: values.status,
  }
}

export function toCatalogItemFormValues(item: {
  articleCode: string
  barcode: string | null
  name: string
  description: string | null
  weight: number | string | null
  volume: number | string | null
  length: number | string | null
  width: number | string | null
  height: number | string | null
  assemblyTimeMinutes: number | null
  status: string
}): CatalogItemFormValues {
  const text = (value: number | string | null) => (value === null ? '' : String(value))

  return {
    articleCode: item.articleCode,
    barcode: item.barcode ?? '',
    name: item.name,
    description: item.description ?? '',
    weight: text(item.weight),
    volume: text(item.volume),
    length: text(item.length),
    width: text(item.width),
    height: text(item.height),
    assemblyTimeMinutes: text(item.assemblyTimeMinutes),
    status: item.status,
  }
}
