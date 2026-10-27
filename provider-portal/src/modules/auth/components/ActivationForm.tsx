import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { z } from 'zod'

import { paths } from '@/app/router/paths'
import { errorMessage } from '@/shared/api/errors'
import { Field } from '@/shared/components/form/Field'
import { applyServerErrors } from '@/shared/components/form/serverErrors'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'

import { authApi, type InvitationPreview } from '../api/authApi'
import { mismatch, newPasswordFields, passwordsMatch } from '../passwordSchema'

const identity = {
  firstName: z.string().trim().min(1, 'Prénom requis.').max(255),
  lastName: z.string().trim().min(1, 'Nom requis.').max(255),
}

const withPassword = z.object({ ...identity, ...newPasswordFields }).refine(passwordsMatch, mismatch)
const withoutPassword = z.object({
  firstName: z.string().optional(),
  lastName: z.string().optional(),
  password: z.string().optional(),
  password_confirmation: z.string().optional(),
})

type Values = z.infer<typeof withoutPassword>

const FIELDS = ['firstName', 'lastName', 'password', 'password_confirmation'] as const

export function ActivationForm({ token, invitation }: { token: string; invitation: InvitationPreview }) {
  const navigate = useNavigate()
  const [error, setError] = useState<string | null>(null)
  const form = useForm<Values>({
    resolver: zodResolver(invitation.requiresPassword ? withPassword : withoutPassword),
    defaultValues: { firstName: invitation.firstName, lastName: invitation.lastName, password: '', password_confirmation: '' },
  })

  const submit = async (values: Values) => {
    setError(null)

    try {
      const notice = await authApi.activate(invitation.requiresPassword ? { token, ...values } : { token })
      navigate(paths.login, { replace: true, state: { notice } })
    } catch (caught) {
      const orphans = applyServerErrors(form, caught, FIELDS)
      setError(orphans[0] ?? (orphans.length === 0 ? errorMessage(caught) : null))
    }
  }

  return (
    <form className="space-y-4" noValidate onSubmit={form.handleSubmit(submit)}>
      {error && (
        <Alert variant="destructive" role="alert">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}
      {invitation.requiresPassword ? (
        <>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field form={form} name="firstName" label="Prénom" autoComplete="given-name" className="bg-muted/40 h-12 rounded-xl" />
            <Field form={form} name="lastName" label="Nom" autoComplete="family-name" className="bg-muted/40 h-12 rounded-xl" />
          </div>
          <Field form={form} name="password" label="Mot de passe" type="password" autoComplete="new-password" className="bg-muted/40 h-12 rounded-xl" />
          <Field form={form} name="password_confirmation" label="Confirmation" type="password" autoComplete="new-password" className="bg-muted/40 h-12 rounded-xl" />
        </>
      ) : (
        <p className="text-muted-foreground text-sm">
          Un compte Tricolis existe déjà pour cette adresse. Activez l’accès, puis connectez-vous avec votre mot de passe
          habituel.
        </p>
      )}
      <Button type="submit" className="from-primary to-primary/80 h-12 w-full rounded-xl bg-linear-to-r font-semibold" disabled={form.formState.isSubmitting}>
        Activer mon accès
      </Button>
    </form>
  )
}
