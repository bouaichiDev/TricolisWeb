import { z } from 'zod'

/** Longueurs reprises de `StoreRoleRequest`. */
export const roleSchema = z.object({
  code: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  name: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  scope: z.string().max(40, 'validation.max'),
  status: z.string().min(1, 'validation.required'),
})

export type RoleFormValues = z.infer<typeof roleSchema>

export const ROLE_FORM_DEFAULTS: RoleFormValues = {
  code: '',
  name: '',
  scope: '',
  status: 'active',
}

export function scopeOrNull(scope: string): string | null {
  return scope.trim() === '' ? null : scope.trim()
}
