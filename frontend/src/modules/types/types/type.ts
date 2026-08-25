/**
 * Référentiels de type — `TypeResource` et `TypeItemResource`.
 *
 * `types` porte la source (véhicule, colis, groupage…), `type_items` ses
 * valeurs. Les trois référentiels qui avaient chacun leur table vivent
 * désormais ici, ce qui permet à un organisme d'en ajouter d'autres sans
 * modification du schéma.
 */
export interface TypeSource {
  id: string
  organizationId: string
  code: string
  name: string
  status: string
  /** Vrai quand une colonne du schéma s'y réfère : code figé, suppression fermée. */
  isSystem: boolean
  itemCount?: number
  createdAt: string
  updatedAt: string
}

export interface TypeItem {
  id: string
  organizationId: string
  typeId: string
  typeCode?: string
  code: string
  name: string
  status: string
  position: number
  createdAt: string
  updatedAt: string
}

export const TYPE_STATUSES = ['active', 'inactive'] as const

/** Codes des sources auxquelles le schéma se réfère. */
export const SYSTEM_TYPE_CODES = ['vehicle', 'package', 'grouping'] as const

export interface TypeSourcePayload {
  code: string
  name: string
  status?: string
}

export interface TypeItemPayload {
  typeId: string
  code: string
  name: string
  status?: string
  position?: number
}

export interface TypeItemFilters {
  page: number
  perPage: number
  typeId?: string
  type?: string
  search?: string
  status?: string
}
