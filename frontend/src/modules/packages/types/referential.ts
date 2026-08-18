/**
 * Type de colis et type de regroupement — `ReferentialResource`.
 *
 * Les deux référentiels partagent exactement le même contrat : `code`, `name`,
 * `status`. Un seul type et un seul formulaire les servent donc, paramétrés par
 * leur chemin. Les dédoubler produirait deux fois le même code.
 */
export interface PackageReferential {
  id: string
  organizationId: string
  code: string
  name: string
  status: string
  createdAt: string
  updatedAt: string
}

export const REFERENTIAL_STATUSES = ['active', 'inactive'] as const

/**
 * Les deux référentiels sont gouvernés par `packages.*`.
 *
 * `PermissionSeeder` le dit explicitement : aucune permission propre n'existe,
 * et en inventer une produirait un code que rien ne vérifie côté serveur.
 */
export type ReferentialKind = 'package-types' | 'package-grouping-types'

export interface ReferentialFilters {
  page: number
  perPage: number
  search?: string
}
