import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { mismatch, newPasswordFields, passwordsMatch } from '@/modules/auth/passwordSchema'
import { errorMessage } from '@/shared/api/errors'
import { Field } from '@/shared/components/form/Field'
import { applyServerErrors } from '@/shared/components/form/serverErrors'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'

import { profileApi, type PasswordChange } from '../api/profileApi'

const schema = z
  .object({ currentPassword: z.string().min(1, 'Mot de passe actuel requis.'), ...newPasswordFields })
  .refine(passwordsMatch, mismatch)

const EMPTY: PasswordChange = { currentPassword: '', password: '', password_confirmation: '' }

/**
 * Changer son mot de passe. L'ancien est exigé ; le serveur ferme alors les
 * autres sessions du portail et garde celle-ci.
 */
export function PasswordForm() {
  const [error, setError] = useState<string | null>(null)
  const [done, setDone] = useState<string | null>(null)
  const form = useForm<PasswordChange>({ resolver: zodResolver(schema), defaultValues: EMPTY })

  const submit = async (values: PasswordChange) => {
    setError(null)
    setDone(null)

    try {
      setDone(await profileApi.changePassword(values))
      form.reset(EMPTY)
    } catch (caught) {
      const orphans = applyServerErrors(form, caught, ['currentPassword', 'password', 'password_confirmation'])
      setError(orphans[0] ?? (orphans.length === 0 ? errorMessage(caught) : null))
    }
  }

  return (
    <SectionCard title="Mot de passe" description="Changer de mot de passe ferme vos autres sessions ouvertes sur le portail.">
      <form className="space-y-4" noValidate onSubmit={form.handleSubmit(submit)}>
        {error && (
          <Alert variant="destructive" role="alert">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}
        {done && (
          <Alert role="status">
            <AlertDescription>{done}</AlertDescription>
          </Alert>
        )}
        <Field form={form} name="currentPassword" label="Mot de passe actuel" type="password" autoComplete="current-password" />
        <div className="grid gap-4 sm:grid-cols-2">
          <Field form={form} name="password" label="Nouveau mot de passe" type="password" autoComplete="new-password" />
          <Field form={form} name="password_confirmation" label="Confirmer le mot de passe" type="password" autoComplete="new-password" />
        </div>
        <Button type="submit" disabled={form.formState.isSubmitting}>
          Changer le mot de passe
        </Button>
      </form>
    </SectionCard>
  )
}
