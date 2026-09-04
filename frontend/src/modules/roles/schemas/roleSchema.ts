import { z } from 'zod'

/**
 * Longueurs reprises de `StoreRoleRequest`.
 *
 * `scope` et `isSystem` sont absents : l'API ne les accepte plus en entrée et
 * les impose. Les garder ici produirait un formulaire dont une partie serait
 * silencieusement ignorée.
 */
export const roleSchema = z.object({
  code: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  name: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  status: z.string().min(1, 'validation.required'),
})

export type RoleFormValues = z.infer<typeof roleSchema>

export const ROLE_FORM_DEFAULTS: RoleFormValues = {
  code: '',
  name: '',
  status: 'active',
}
