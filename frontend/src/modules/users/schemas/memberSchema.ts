import { z } from 'zod'

/**
 * Champs communs à la création et à la modification d'un membre.
 *
 * `email` et `password` n'y figurent pas : ils ne sont acceptés qu'à la
 * création, et le mot de passe se change ensuite par `PATCH /auth/password`.
 */
const memberBase = {
  firstName: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  lastName: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  phone: z.string().max(255, 'validation.max'),
  preferredLanguage: z.string().min(1, 'validation.required').max(10, 'validation.max'),
  isOwner: z.boolean(),
  isPrimary: z.boolean(),
  status: z.string().min(1, 'validation.required'),
}

export const memberCreateSchema = z
  .object({
    ...memberBase,
    email: z.string().min(1, 'validation.required').max(255).email('validation.email'),
    password: z.string().min(8, 'validation.password'),
    passwordConfirmation: z.string().min(1, 'validation.required'),
  })
  .refine((values) => values.password === values.passwordConfirmation, {
    message: 'validation.passwordMismatch',
    path: ['passwordConfirmation'],
  })

export const memberUpdateSchema = z.object(memberBase)

export type MemberCreateValues = z.infer<typeof memberCreateSchema>
export type MemberUpdateValues = z.infer<typeof memberUpdateSchema>

export const MEMBER_CREATE_DEFAULTS: MemberCreateValues = {
  firstName: '',
  lastName: '',
  email: '',
  phone: '',
  password: '',
  passwordConfirmation: '',
  preferredLanguage: 'fr',
  isOwner: false,
  isPrimary: false,
  status: 'active',
}

export const MEMBER_UPDATE_DEFAULTS: MemberUpdateValues = {
  firstName: '',
  lastName: '',
  phone: '',
  preferredLanguage: 'fr',
  isOwner: false,
  isPrimary: false,
  status: 'active',
}

export function phoneOrNull(phone: string): string | null {
  return phone.trim() === '' ? null : phone.trim()
}
