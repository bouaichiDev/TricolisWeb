import { z } from 'zod'

/**
 * Le minimum que le backend exige (`Password::defaults()` : 8 caractères).
 * Il reste seul juge : une règle plus stricte côté serveur revient en 422.
 */
export const newPasswordFields = {
  password: z.string().min(8, 'Au moins 8 caractères.'),
  password_confirmation: z.string(),
}

export function passwordsMatch(values: { password?: string; password_confirmation?: string }): boolean {
  return values.password === values.password_confirmation
}

export const mismatch = {
  message: 'Les deux mots de passe ne correspondent pas.',
  path: ['password_confirmation'],
}
